<?php

namespace App\Controller;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class SitemapController extends AbstractController
{
    /**
     * @Route("/sitemap.xml", name="sitemap")
     */
    public function sitemap(RouterInterface $router)
    {
        $routes = [];

        foreach ($router->getRouteCollection()->all() as $route_name => $route) {
            if (!in_array($route_name, ['sitemap', 'signout']) &&
                substr($route_name, 0, 1) != "_" &&  # Remove internal Symfony routes
                strpos($route_name, 'app_') === false && # Remove app routes
                strpos($route_name, 'api_') === false && # Remove api routes
                strpos($route->getPath(), '{') === false && # Remove routes requiring parameters
                strpos($route->getPath(), '/modal/') === false) # Remove routes pointing to ajax fetchable modals
            {
                $routes[$route_name] = 'https://voiceep.com' . $route->getPath();
            }
        }

        return $this->render('sitemap.xml.twig', ['routes' => $routes]);
    }
}
