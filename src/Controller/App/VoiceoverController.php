<?php

namespace App\Controller\App;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Entity\Entry;
use App\Entity\Voiceover;
use App\Helper\AWS\Polly;
use App\Helper\AWS\S3;
use App\Helper\AWS\CloudFront;

/**
 * @Route("/app/voiceover", name="app_voiceover_")
 */
class VoiceoverController extends AppController
{

    /**
     * @Route("/listing", name="listing")
     */
    public function listing()
    {
        if (!$this->getUser()->getProjects()->count()) {
            return $this->redirectToRoute('app_project_create');
        }
        return $this->render('page/protected/voiceover/listing.html.twig',
                            ['project' => $this->getCurrentProjectFromSession()]);
    }

    /**
     * @Route("/create", name="create")
     * @Route("/edit", name="edit")
     */
    public function edit(Request $request)
    {
        if (!$this->getUser()->getProjects()->count()) {
            return $this->redirectToRoute('app_project_create');
        }

        $project = $this->getCurrentProjectFromSession();
        $editMode = false;

        if ($voiceoverId = $request->query->get('voiceover_id')) {
            $voiceover = $this->getDoctrine()
                        ->getRepository(Voiceover::class)
                        ->find($voiceoverId);
            $editMode = true;
        } else {
            $voiceover = (new Voiceover())->setStatus('scheduled')
                                        ->setLanguageCode($project->getLatestVoiceoverLanguage());
        }

        $form = $this->createFormBuilder($voiceover)
            ->add('entry', EntityType::class, ['label' => 'Parent entry',
                                                'class' => Entry::class,
                                                'choices' => $project->getEntries()])
            ->add('type', ChoiceType::class, ['label' => 'Type', 'choices' => Voiceover::getTypeSelectOptions()])
            ->add('languageCode', ChoiceType::class, ['label' => 'Language', 'choices' => Polly::getAvailableLanguageCodes(true)])
            ->add('file', FileType::class, ['label' => 'Audio file', 'required' => false, 'mapped' => false])
            ->add('text', TextareaType::class, ['label' => 'Custom text',
                                                'required' => false,
                                                'attr' => ['placeholder' => 'When provided, this text will be used to create the voiceover instead of the page\'s text.']])
            ->add('create', SubmitType::class, ['label' => ($editMode ? 'Update' : 'Create') . ' voiceover', 'attr' => ['class' => 'large']])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                #$voiceover = $form->getData();
                $em = $this->getDoctrine()->getManager();

                if ($voiceover->getType() != 'polly') {
                    # Validate language code for selected type
                    if (!$voiceover->getLanguageCode()) {
                        $form['languageCode']->addError(new FormError("Please select the language of your text."));
                    } else if ($voiceover->getType() != 'manual' && !in_array(substr($voiceover->getLanguageCode(), 0, 3), ['en-', 'fr-'])) {
                        $form['type']->addError(new FormError("Sorry, we currently do not support narration requests for this language."));
                    }

                    if ($voiceover->getType() == 'manual') {
                        # Handle file upload
                        $file = $form['file']->getData();
                        if ($file) {
                            $fileMimeType = $file->getMimeType();
                            try {
                                if (in_array($fileMimeType, Voiceover::getAcceptedFileTypes())) {
                                    if (!$form->getErrors()->count()) {
                                        $S3Response = S3::uploadFile($file->getPathname(), 'manual');

                                        if ($S3Response && $S3Response['ObjectURL']) {
                                            $cloudfrontUrl = CloudFront::buildUrlFromS3Url($S3Response['ObjectURL']);
                                            $voiceover->setStatus('completed')
                                                    ->setOption('file_url', $cloudfrontUrl)
                                                    ->setOption('file_format', substr($fileMimeType, strpos($fileMimeType, '/') + 1));
                                        } else {
                                            $form->addError(new FormError("Sorry, an error occured while uploading your file to our storage servers. Please try again."));
                                        }
                                    }
                                } else {
                                    $form->addError(new FormError("This type of file is not allowed. Here are the accepted file types: " . implode(', ', $acceptedMimeTypes) . '.'));
                                }
                            } catch (\Exception $e) {
                                $form->addError(new FormError("The provided audio file is either too large or invalid."));
                            }
                        } else if (!$voiceover->getOption('file_url')) {
                            $form->addError(new FormError("You must provide an audio file for the voiceover when selecting the manual upload type."));
                        }
                    }
                } else {
                    if (!$voiceover->getText()) {
                        $form['text']->addError(new FormError("The custom text field must be provided when manually creating a voiceover using server-generated text-to-speech."));
                    }
                }

                if (!$form->getErrors()->count()) {
                    $em->persist($voiceover);
                    $em->flush();

                    return $this->redirectToRoute('app_voiceover_edit', ['voiceover_id' => $voiceover->getId(), 'success' => true]);
                }
            }
        }

        return $this->render('page/protected/voiceover/edit.html.twig',
                            ['form' => $form->createView(),
                             'voiceover' => $voiceover,
                             'success' => $request->query->get('success')]);
    }

    /**
     * @Route("/record", name="record")
     */
    public function record(Request $request)
    {
        if (!$this->getUser()->getProjects()->count()) {
            return $this->redirectToRoute('app_project_create');
        }

        $project = $this->getCurrentProjectFromSession();
        $voiceover = (new Voiceover())->setStatus('inprogress')
                                    ->setType('manual')
                                    ->setLanguageCode($project->getLatestVoiceoverLanguage());

        if ($entryId = $request->query->get('entry_id')) {
            $entry = $this->getDoctrine()
                        ->getRepository(Entry::class)
                        ->find($entryId);
            if ($entry && $entry->getProject() == $project) {
                $voiceover->setEntry($entry);
            }
        }

        $form = $this->createFormBuilder($voiceover)
            ->add('entry', EntityType::class, ['label' => 'Parent entry',
                                                'class' => Entry::class,
                                                'choices' => $project->getEntries()])
            ->add('languageCode', ChoiceType::class, ['label' => 'Language', 'choices' => Polly::getAvailableLanguageCodes(true)])
            ->add('file', TextType::class, ['label' => 'Audio file', 'required' => false, 'mapped' => false])
            ->add('save', SubmitType::class, ['label' => 'Save voiceover', 'attr' => ['class' => 'large']])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $recordingUrl = $form['file']->getData();
                if ($recordingUrl) {
                    $voiceover->setStatus('completed')
                        ->setOption('file_url', $recordingUrl)
                        ->setOption('file_format', 'mp3');

                    $em = $this->getDoctrine()->getManager();
                    $em->persist($voiceover);
                    $em->flush();

                    return $this->redirectToRoute('app_voiceover_listing', ['url' => $voiceover->getEntry()->getUrl(), 'notice' => "Your voiceover has been saved successfully."]);
                }
            }
        }

        return $this->render('page/protected/voiceover/record.html.twig',
                            ['form' => $form->createView(),
                             'voiceover' => $voiceover]);
    }

    /**
     * @Route("/record/upload-blob", name="record_upload_blob")
     */
    public function uploadBlob(Request $request)
    {
        $response = [];

        $blob = file_get_contents('php://input');
        $S3Response = S3::uploadBlob($blob, 'recording');

        if ($S3Response && $S3Response['ObjectURL']) {
            $response['url'] = CloudFront::buildUrlFromS3Url($S3Response['ObjectURL']);
        } else {
            $response['error'] = "Sorry, an error occured while uploading your file to our storage servers. Please try again.";
        }

        return new JsonResponse($response);
    }

    /**
     * @Route("/delete", name="delete")
     */
    public function delete(Request $request)
    {
        if (!$this->getUser()->getProjects()->count()) {
            return $this->redirectToRoute('app_project_create');
        }

        $voiceoverId = $request->query->get('voiceover_id');
        if ($voiceoverId) {
            $voiceover = $this->getDoctrine()
                        ->getRepository(Voiceover::class)
                        ->find($voiceoverId);

            if ($voiceover->getEntry()->getProject()->getUser() == $this->getUser()) {
                $em = $this->getDoctrine()->getManager();
                $voiceover->delete($em);
                $em->flush();
            } else {
                throw $this->createAccessDeniedException("You are not allowed to access this voiceover.");
            }
        }

        return $this->redirectToReferer($request);
    }
}
