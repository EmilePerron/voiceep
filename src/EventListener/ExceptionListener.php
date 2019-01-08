<?php

namespace App\EventListener;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\User;

class ExceptionListener
{
	protected $em;
	protected $tokenStorage;
	protected $request;
	protected $swiftmailer;

    function __construct(TokenStorageInterface $tokenStorage, RequestStack $requestStack, \Swift_Mailer $swiftmailer)
    {
		$this->tokenStorage = $tokenStorage;
		$this->requestStack = $requestStack;
        $this->swiftmailer = $swiftmailer;
    }

	# When an error occurs, automatically send a bug report via email
	public function onKernelException(GetResponseForExceptionEvent $event)
    {
		# Ignore myself
		if (($_SERVER['REMOTE_ADDR'] ?? '') == "64.229.58.84") {
			return;
        }

        $exception = $event->getException();
		$token = $this->tokenStorage->getToken();

        $user = null;
		if ($token && $token->getUser() instanceof User) {
            $user = $token->getUser();
        }

		if ($exception->getStatusCode() == 404) {
			return;
		}

		$code = ($exception instanceof \HttpExceptionInterface) ? $exception->getStatusCode() : Response::HTTP_INTERNAL_SERVER_ERROR;
		$request = $this->requestStack->getCurrentRequest();
		$currentUrl = $request ? $request->getUri() : null;


        $emailContent = "[Automated bug report - Error " . $code . " - User " . ($user ? $user->getId() : 'unknown') . "]\n" .
                           "" . $exception->getMessage() . "\n" .
                           ($currentUrl ? "" . $currentUrl . "\n" : '') .
                           (isset($_SERVER['HTTP_REFERER']) ? "\nReferer: " . $_SERVER['HTTP_REFERER'] . "\n" : "\n") .
                           "GET: " . json_encode($request->query) . "\n" .
                           "POST: " . json_encode($request->request);

		$message = (new \Swift_Message("An error " . $code . " has occured on Voiceep at " . date("d/m/Y H:i:s") . ""))
			->setFrom(['noreply@voiceep.com' => 'Voiceep'])
			->setTo('bugs@voiceep.com')
			->setBody(nl2br($emailContent), 'text/html');

		$this->swiftmailer->send($message);
    }
}
