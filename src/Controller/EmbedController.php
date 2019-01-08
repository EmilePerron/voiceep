<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class EmbedController extends Controller
{
    /**
     * @Route("/{project_id}/embed.js", name="embed")
     */
    public function embed($project_id)
    {
        # TODO : validate project ID

        header('Content-Type: application/javascript');

        if (($_SERVER['APP_ENV'] ?? 'dev') == 'dev') {
            header("Cache-Control: no-cache, must-revalidate"); //HTTP 1.1
            header("Pragma: no-cache"); //HTTP 1.0
            header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
        } else {
            header("Cache-Control: max-age=3600");
        }

        $embedFilePath = $this->get('kernel')->getProjectDir() . '/public/embed.js';
        $embedJS = file_get_contents($embedFilePath);
        $embedJS = str_replace("_PROJECT_ID_", $project_id, $embedJS);

        die($embedJS);
    }
}
