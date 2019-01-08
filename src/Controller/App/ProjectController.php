<?php

namespace App\Controller\App;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Entity\Project;
use App\Helper\DOM;
use App\Helper\AWS\Polly;
use App\Form\Type\TextArrayType;

/**
 * @Route("/app/project", name="app_project_")
 */
class ProjectController extends AppController
{
    /**
     * @Route("/create", name="create")
     */
    public function create(Request $request)
    {
        $project = new Project();

        $availableLanguageCodes = ['Detect automatically' => null] + Polly::getAvailableLanguageCodes(true);

        $form = $this->createFormBuilder($project)
            ->add('title', TextType::class, ['label' => 'Project title', 'attr' => ['placeholder' => 'My project name']])
            ->add('defaultLanguageCode', ChoiceType::class, ['label' => 'Default language', 'choices' => $availableLanguageCodes])
            ->add('allowedDomains', TextArrayType::class, ['label' => 'Allowed domain(s)', 'attr' => ['placeholder' => 'www.mydomain.com']])
            ->add('detectSelector', ChoiceType::class, ['label' => 'Technical knowledge',
                                                        'choices' => ["I don't know anything about coding" => true, "I know some HTML and Javascript" => false],
                                                        'expanded' => true,
                                                        'mapped' => false])
            ->add('create', SubmitType::class, ['label' => 'Create project', 'attr' => ['class' => 'large']])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $project = $form->getData();
                $project->setUser($this->getUser());

                $em = $this->getDoctrine()->getManager();
                $em->persist($project);
                $em->flush();

                # We need the ID to create the API key
                $project->setApiKey($project->generateApiKey());
                $em->persist($project);
                $em->flush();

                $this->get('session')->set('current_project_id', $project->getId());

                if ($form['detectSelector']->getData()) {
                    return $this->redirectToRoute('app_project_detect_selector');
                } else {
                    return $this->redirectToRoute('app_embed');
                }
            }
        }

        return $this->render('page/protected/project/create.html.twig',
                            ['form' => $form->createView()]);
    }

    /**
     * @Route("/edit", name="edit")
     */
    public function edit(Request $request)
    {
        if (!$this->getUser()->getProjects()->count()) {
            return $this->redirectToRoute('app_project_create');
        }

        $project = $this->getCurrentProjectFromSession();

        $availableLanguageCodes = ['Detect automatically' => null] + Polly::getAvailableLanguageCodes(true);

        $form = $this->createFormBuilder($project)
            ->add('title', TextType::class, ['label' => 'Project title', 'attr' => ['placeholder' => 'My project name']])
            ->add('defaultLanguageCode', ChoiceType::class, ['label' => 'Default language', 'choices' => $availableLanguageCodes])
            ->add('defaultContentSelector', TextType::class, ['label' => 'Default content selector', 'attr' => ['placeholder' => '.article .content'],
                                                                'help' => "Don't know what a selector is? <a href='" . $this->generateUrl('app_project_detect_selector') . "'>Click here to detect it automatically</a>."])
            ->add('allowedDomains', TextArrayType::class, ['label' => 'Allowed domain(s)', 'attr' => ['placeholder' => 'www.mydomain.com']])
            ->add('save', SubmitType::class, ['label' => 'Update project', 'attr' => ['class' => 'large']])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $project = $form->getData();
                $project->setUser($this->getUser());

                $em = $this->getDoctrine()->getManager();
                $em->persist($project);
                $em->flush();

                $this->get('session')->set('current_project_id', $project->getId());

                return $this->redirectToRoute('app_project_edit', ['saved' => true]);
            }
        }

        return $this->render('page/protected/project/edit.html.twig',
                            ['form' => $form->createView(),
                             'project' => $project,
                             'success' => $request->query->get('saved')]);
    }

    /**
     * @Route("/delete", name="delete")
     */
    public function delete(Request $request)
    {
        if (!$this->getUser()->getProjects()->count()) {
            return $this->redirectToRoute('app_project_create');
        }

        $projectId = $request->query->get('project_id');
        if ($projectId) {
            $project = $this->getDoctrine()
                        ->getRepository(Project::class)
                        ->find($projectId);

            if ($project->getUser() == $this->getUser()) {
                $em = $this->getDoctrine()->getManager();
                $project->delete($em);
                $em->flush();

                if ($this->get('session')->get('current_project_id') == $projectId) {
                    $this->get('session')->set('current_project_id', null);
                }
            } else {
                throw $this->createAccessDeniedException("You are not allowed to access this project.");
            }
        }

        return $this->redirectToRoute('app_dashboard');
    }

    /**
     * @Route("/detect-selector", name="detect_selector")
     */
     public function detectSelector(Request $request) {
         if (!$this->getUser()->getProjects()->count()) {
             return $this->redirectToRoute('app_project_create');
         }

         $project = $this->getCurrentProjectFromSession();

         $form = $this->createFormBuilder($project)
             ->add('url', UrlType::class, ['label' => 'Provide a link to one of your articles',
                                           'attr' => ['placeholder' => 'Link to one of your articles'],
                                           'mapped' => false ])
             ->add('content', TextareaType::class, ['label' => 'Copy and paste the text from that article here',
                                           'attr' => ['placeholder' => 'Content of the article'],
                                           'mapped' => false ])
             ->add('save', SubmitType::class, ['label' => 'Detect the selector', 'attr' => ['class' => 'large']])
             ->getForm();

         $form->handleRequest($request);

         if ($form->isSubmitted()) {
             if ($form->isValid()) {
                $project = $form->getData();
                $project->setUser($this->getUser());

                try {
                    $detectedSelector = DOM::fromUrl($form['url']->getData())
                                            ->findElementFromContent($form['content']->getData(), true);
                } catch (\Exception $e) {
                    $form->addError(new FormError($e->getMessage()));
                }

                if (!$form->getErrors()->count()) {
                    $project->setDefaultContentSelector($detectedSelector);

                    $em = $this->getDoctrine()->getManager();
                    $em->persist($project);
                    $em->flush();

                    $this->get('session')->set('current_project_id', $project->getId());

                    return $this->redirectToRoute('app_project_edit', ['saved' => true]);
                }
             }
         }

         return $this->render('page/protected/project/detect_selector.html.twig',
                             ['form' => $form->createView(),
                              'project' => $project,
                              'success' => $request->query->get('saved')]);
     }
}
