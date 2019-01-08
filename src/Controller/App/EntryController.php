<?php

namespace App\Controller\App;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Entity\Entry;
use App\Entity\Voiceover;
use App\Helper\AWS\Polly;

/**
 * @Route("/app/entry", name="app_entry_")
 */
class EntryController extends AppController
{
    /**
     * @Route("/listing", name="listing")
     */
    public function listing(Request $request)
    {
        if (!$this->getUser()->getProjects()->count()) {
            return $this->redirectToRoute('app_project_create');
        }
        return $this->render('page/protected/entry/listing.html.twig',
                            ['project' => $this->getCurrentProjectFromSession()]);
    }
    /**
     * @Route("/create", name="create")
     */
    public function create(Request $request)
    {
        if (!$this->getUser()->getProjects()->count()) {
            return $this->redirectToRoute('app_project_create');
        }

        $project = $this->getCurrentProjectFromSession();
        $entry = (new Entry())->setProject($project);;

        $form = $this->createFormBuilder($entry)
            ->add('url', TextType::class, ['label' => 'URL', 'attr' => ['placeholder' => 'www.myblog.com/article/my-title']])
            ->add('title', TextType::class, ['label' => 'Title', 'attr' => ['placeholder' => 'My article title']])
            ->add('voiceover', ChoiceType::class, ['label' => 'Voiceover',
                                                   'mapped' => false,
                                                   'choices' => Voiceover::getTypeSelectOptions("Let the embed script handle the voiceover generation (default)")])
            ->add('language', ChoiceType::class, ['label' => 'Language',
                                                  'choices' => Polly::getAvailableLanguageCodes(true),
                                                  'required' => false,
                                                  'mapped' => false])
            ->add('create', SubmitType::class, ['label' => 'Create entry', 'attr' => ['class' => 'large']])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($valid = $form->isValid()) {
                $em = $this->getDoctrine()->getManager();

                if (!$project->checkIfUrlMatchesAllowedDomains($entry->getUrl())) {
                    $valid = false;
                    $unallowedDomainError = new FormError("This URL does not match the allowed domains for this project.");
                    $form->get('url')->addError($unallowedDomainError);
                }

                $existingEntry = $this->getDoctrine()->getRepository(Entry::class)->findOneFromProjectByUrl($project, $entry->getUrl());
                if ($existingEntry) {
                    $valid = false;
                    $duplicateUrlError = new FormError("You already have an entry for that URL.");
                    $form->get('url')->addError($duplicateUrlError);
                }

                $voiceoverType = $form->get('voiceover')->getData();
                if ($voiceoverType != 'polly') {
                    $language = $form->get('language')->getData();

                    if (!$language) {
                        $valid = false;
                        $undefinedLanguageError = new FormError("Please select the language of your text.");
                        $form->get('language')->addError($undefinedLanguageError);
                    } else if ($voiceoverType != 'manual' && !in_array(substr($language, 0, 3), ['en-', 'fr-'])) {
                        $valid = false;
                        $unsupportedLanguageError = new FormError("Sorry, we currently do not support narration requests for this language.");
                        $form->get('voiceover')->addError($unsupportedLanguageError);
                    }

                    if ($valid) {
                        $voiceover = (new Voiceover())
                                    ->setEntry($entry)
                                    ->setType($voiceoverType)
                                    ->setStatus('scheduled')
                                    ->setLanguageCode($language)
                                    ->setText('');
                        $em->persist($voiceover);
                    }
                }

                if ($valid) {
                    $em->persist($entry);
                    $em->flush();

                    return $this->redirectToRoute('app_voiceover_listing', ['q' => 'url:' . $entry->getUrl()]);
                }
            }
        }

        return $this->render('page/protected/entry/create.html.twig',
                            ['form' => $form->createView()]);
    }

    /**
     * @Route("/edit", name="edit")
     */
    public function edit(Request $request)
    {

    }

    /**
     * @Route("/delete", name="delete")
     */
    public function delete(Request $request)
    {
        if (!$this->getUser()->getProjects()->count()) {
            return $this->redirectToRoute('app_project_create');
        }

        $entryId = $request->query->get('entry_id');
        if ($entryId) {
            $entry = $this->getDoctrine()
                        ->getRepository(Entry::class)
                        ->find($entryId);

            if ($entry->getProject()->getUser() == $this->getUser()) {
                $em = $this->getDoctrine()->getManager();
                $entry->delete($em);
                $em->flush();
            } else {
                throw $this->createAccessDeniedException("You are not allowed to access this entry.");
            }
        }

        return $this->redirectToReferer($request);
    }
}
