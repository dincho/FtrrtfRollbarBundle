<?php

namespace spec\Ftrrtf\RollbarBundle\EventListener;

use Ftrrtf\Rollbar\Environment;
use Ftrrtf\Rollbar\ErrorHandler;
use Ftrrtf\Rollbar\Notifier;
use Ftrrtf\RollbarBundle\Helper\UserHelper;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\Console\Event\ConsoleExceptionEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class RollbarListenerSpec extends ObjectBehavior
{
    function let(
        Notifier $notifier,
        ErrorHandler $errorHandler,
        SecurityContextInterface $securityContext,
        Environment $environment,
        UserHelper $userHelper
    ) {
        $notifier->getEnvironment()->willReturn($environment);
        $environment->setOption('person_callback', Argument::type('\Closure'))->shouldBeCalled();

        $this->beConstructedWith($notifier, $errorHandler, $securityContext, $userHelper);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('Ftrrtf\RollbarBundle\EventListener\RollbarListener');
    }

    function it_register_handlers_on_kernel_request(
        ErrorHandler $errorHandler,
        Notifier $notifier,
        GetResponseEvent $event
    ) {
        $errorHandler->registerErrorHandler($notifier)->shouldBeCalled();
        $errorHandler->registerShutdownHandler($notifier)->shouldBeCalled();

        $this->onKernelRequest($event);
    }

    function it_catch_exception(GetResponseForExceptionEvent $event, \Exception $exception)
    {
        $event->getException()->willReturn($exception);
        $this->onKernelException($event);
        $this->getException()->shouldReturn($exception);
    }

    function it_skip_HTTP_exception(GetResponseForExceptionEvent $event, HttpException $httpException)
    {
        $event->getException()->willReturn($httpException);
        $this->onKernelException($event);
        $this->getException()->shouldReturn(null);
    }

    function it_report_exception_on_console_exception(Notifier $notifier, \Exception $exception, ConsoleExceptionEvent $event)
    {
        $this->setException($exception);
        $event->getException()->willReturn($exception);

        $notifier->reportException($exception)->shouldBeCalled();
        $this->onConsoleException($event);
    }

    function it_report_exception_on_kernel_response(Notifier $notifier, \Exception $exception, FilterResponseEvent $event)
    {
        $this->setException($exception);
        $notifier->reportException($exception)->shouldBeCalled();
        $this->onKernelResponse($event);
    }

    function it_clear_exception_after_report(Notifier $notifier, \Exception $exception, FilterResponseEvent $event)
    {
        $this->setException($exception);

        $notifier->reportException($exception)->shouldBeCalled();
        $this->onKernelResponse($event);

        $this->getException()->shouldReturn(null);
    }

    function it_skip_report_if_there_is_no_exception_on_kernel_response(Notifier $notifier, FilterResponseEvent $event)
    {
        $this->setException(null);
        $notifier->reportException(Argument::any())->shouldNotBeCalled();
        $this->onKernelResponse($event);
    }

    function it_should_skip_user_data_if_user_is_not_defined(
        SecurityContextInterface $securityContext,
        TokenInterface $token,
        UserHelper $userHelper
    ) {
        $securityContext->isGranted('IS_AUTHENTICATED_REMEMBERED')->willReturn(true);
        $securityContext->getToken()->willReturn($token);
        $token->getUser()->willReturn(null);

        $userHelper->buildUserData(Argument::any())->shouldNotBeCalled();

        $this->getUserData()->shouldBeNull();
    }

    function it_should_skip_user_data_if_user_is_anonymous(
        SecurityContextInterface $securityContext,
        TokenInterface $token,
        UserHelper $userHelper
    ) {
        $securityContext->getToken()->willReturn($token);
        $securityContext->isGranted('IS_AUTHENTICATED_REMEMBERED')->willReturn(false);

        $userHelper->buildUserData(Argument::any())->shouldNotBeCalled();

        $this->getUserData()->shouldBeNull();
    }

    function it_get_user_data_if_user_is_defined(
        SecurityContextInterface $securityContext,
        TokenInterface $token,
        UserInterface $user,
        UserHelper $userHelper
    ) {
        $securityContext->isGranted('IS_AUTHENTICATED_REMEMBERED')->willReturn(true);
        $securityContext->getToken()->willReturn($token);
        $token->getUser()->willReturn($user);

        $userHelper->buildUserData($user)->shouldBeCalled();

        $this->getUserData();
    }
}
