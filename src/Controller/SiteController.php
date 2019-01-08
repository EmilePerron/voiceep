<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class SiteController extends AbstractController
{
    /**
     * @Route("/", name="home")
     */
    public function home()
    {
        return $this->render('page/public/home.html.twig');
    }

    /**
     * @Route("/about", name="about")
     */
    public function about()
    {
        return $this->render('page/public/about.html.twig');
    }

    /**
     * @Route("/terms-of-use", name="terms_of_use")
     */
    public function termsOfUse()
    {
        return $this->render('page/public/terms.html.twig');
    }

    /**
     * @Route("/privacy-policy", name="privacy_policy")
     */
    public function privacyPolicy()
    {
        return $this->render('page/public/privacy_policy.html.twig');
    }
}
