<?php

declare(strict_types=1);

namespace Tests\App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\App\Event\TestEvent;
use Tests\App\Mailer\RegistrationMailer;
use Twig\Environment;

final class AppController extends AbstractController
{
    public function dashboard(TokenStorageInterface $tokenStorage): Response
    {
        $token = $tokenStorage->getToken();
        if ($token === null || !is_object($token->getUser())) {
            return new RedirectResponse('/login');
        }

        return new Response('You are in the Dashboard!');
    }

    public function deprecated(LoggerInterface $logger): Response
    {
        trigger_error('Deprecated endpoint', E_USER_DEPRECATED);
        $logger->info('Deprecated endpoint', ['scream' => false]);

        return new Response('Deprecated');
    }

    public function dispatchEvent(EventDispatcherInterface $dispatcher): Response
    {
        $dispatcher->dispatch(new TestEvent());

        return new Response('Event dispatched');
    }

    public function dispatchNamedEvent(EventDispatcherInterface $dispatcher): Response
    {
        $dispatcher->dispatch(new TestEvent(), 'named.event');

        return new Response('Named event dispatched');
    }

    public function dispatchOrphanEvent(EventDispatcherInterface $dispatcher): Response
    {
        $dispatcher->dispatch(new TestEvent(), 'orphan.event');

        return new Response('Orphan event dispatched');
    }

    public function form(Request $request, FormFactoryInterface $formFactory, Environment $twig): Response
    {
        $builder = $formFactory->createNamedBuilder('registration_form', options: ['csrf_protection' => false]);
        $builder->add('email', EmailType::class, ['constraints' => [new NotBlank(), new EmailConstraint()]]);
        $builder->add('password', PasswordType::class, ['constraints' => [new NotBlank()]]);
        $form = $builder->getForm();

        $form->handleRequest($request);

        $status = $form->isSubmitted() && !$form->isValid() ? 422 : 200;

        return new Response($twig->render('security/register.html.twig', ['action' => '']), $status);
    }

    public function httpClientRequests(
        #[Autowire(service: 'app.http_client')]
        HttpClientInterface $httpClient,
        #[Autowire(service: 'app.http_client.json_client')]
        HttpClientInterface $jsonClient,
    ): Response {
        $httpClient->request('GET', 'https://example.com/default', ['headers' => ['X-Test' => 'yes']]);
        $httpClient->request('POST', 'https://example.com/body', ['json' => ['example' => 'payload']]);
        $jsonClient->request('GET', 'https://api.example.com/resource', ['headers' => ['Accept' => 'application/json']]);

        return new Response('HTTP client calls executed');
    }

    public function index(): Response
    {
        return new Response('Hello World!');
    }

    public function login(Environment $twig): Response
    {
        return new Response($twig->render('security/login.html.twig'));
    }

    public function logout(Request $request, TokenStorageInterface $tokenStorage): RedirectResponse
    {
        $tokenStorage->setToken(null);

        $sessionName = null;
        if ($request->hasSession()) {
            $session = $request->getSession();
            $sessionName = $session->getName();
            $session->invalidate();
        }

        $response = new RedirectResponse('/');
        if ($sessionName !== null) {
            $response->headers->clearCookie($sessionName);
        }

        $response->headers->clearCookie('MOCKSESSID');
        $response->headers->clearCookie('REMEMBERME');

        return $response;
    }

    public function redirectToHome(): RedirectResponse
    {
        return new RedirectResponse('/');
    }

    public function redirectToSample(): RedirectResponse
    {
        return new RedirectResponse('/sample');
    }

    public function register(Request $request, Environment $twig): Response
    {
        if ($request->isMethod('POST')) {
            return new RedirectResponse('/dashboard');
        }

        return new Response($twig->render('security/register.html.twig'));
    }

    public function requestWithAttribute(Request $request): Response
    {
        $request->attributes->set('page', 'register');

        return new Response('Request attribute set');
    }

    public function responseJsonFormat(Request $request): JsonResponse
    {
        $request->setRequestFormat('json');

        return new JsonResponse([
            'status' => 'success',
            'message' => "Expected format: 'json'.",
        ]);
    }

    public function responseWithCookie(): Response
    {
        $response = new Response('TESTCOOKIE has been set.');
        $response->headers->setCookie(new Cookie('TESTCOOKIE', 'codecept'));

        return $response;
    }

    public function sample(Request $request, Environment $twig): Response
    {
        $request->attributes->set('foo', 'bar');

        $response = new Response($twig->render('sample.html.twig'), 200, ['X-Test' => '1']);
        $response->headers->setCookie(new Cookie('response_cookie', 'yum'));

        return $response;
    }

    public function sendEmail(RegistrationMailer $mailer): Response
    {
        $mailer->sendConfirmationEmail('jane_doe@example.com');

        return new Response('Email sent');
    }

    public function testPage(Environment $twig): Response
    {
        return new Response($twig->render('test_page.html.twig'));
    }

    public function translation(TranslatorInterface $translator): Response
    {
        $translator->trans('defined_message');

        return new Response('Translation');
    }

    public function twig(Environment $twig): Response
    {
        return new Response($twig->render('home.html.twig'));
    }

    public function unprocessableEntity(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'error',
            'message' => 'The request was well-formed but could not be processed.',
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
