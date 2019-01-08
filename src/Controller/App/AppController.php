<?php

namespace App\Controller\App;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Entity\Project;
use App\Helper\AWS\Polly;
use App\Form\Type\TextArrayType;

/**
 * @Route("/app", name="app_")
 */
class AppController extends AbstractController
{
    public function redirectToReferer(Request $request) {
        return $this->redirect($request->headers->get('referer'));
    }

    protected function getCurrentProjectFromSession() {
        $projectId = $this->get('session')->get('current_project_id');

        if ($projectId) {
            return $this->getDoctrine()
                    ->getRepository(Project::class)
                    ->find($projectId);
        }

        # Fallback on the user's first project
        $fallbackProject = $this->getUser()->getProjects()->first();
        $this->get('session')->set('current_project_id', $fallbackProject->getId());
        return $fallbackProject;
    }

    /**
     * @Route("/dashboard", name="dashboard")
     */
    public function dashboard(Request $request)
    {
        if (!$this->getUser()->getProjects()->count()) {
            return $this->redirectToRoute('app_project_create');
        }

        if ($project_id = $request->query->get('project_id')) {
            foreach ($this->getUser()->getProjects() as $project) {
                if ($project->getId() == $project_id) {
                    $this->get('session')->set('current_project_id', $project->getId());
                    break;
                }
            }
        }

        return $this->render('page/protected/dashboard.html.twig',
                            ['project' => $this->getCurrentProjectFromSession()]);
    }

    /**
     * @Route("/embed-guide", name="embed")
     */
    public function embed()
    {
        if (!$this->getUser()->getProjects()->count()) {
            return $this->redirectToRoute('app_project_create');
        }

        return $this->render('page/protected/embed.html.twig',
                            ['project' => $this->getCurrentProjectFromSession()]);
    }

    /**
     * @Route("/statistics", name="statistics")
     */
    public function statistics()
    {
        if (!$this->getUser()->getProjects()->count()) {
            return $this->redirectToRoute('app_project_create');
        }
        return $this->render('page/protected/statistics.html.twig');
    }

    /**
     * @Route("/support", name="support")
     */
    public function support()
    {
        return $this->render('page/protected/support.html.twig');
    }
}
