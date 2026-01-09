<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Security;

final class BlockedUserSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return;
        }

        if (!$user->isBlocked()) {
            return;
        }

        $route = $event->getRequest()->attributes->get('_route');
        $allowedRoutes = [
            'app_home',
            'app_login',
            'app_register',
            'app_logout',
        ];

        if ($route && in_array($route, $allowedRoutes, true)) {
            return;
        }

        throw new AccessDeniedHttpException('Twoje konto jest zablokowane.');
    }
}
