<?php

namespace App\Service;

use App\Entity\ApiLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class ApiLoggerService
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function log(Request $request, int $responseStatus, ?User $user = null): void
    {
        $log = new ApiLog();
        $log->setEndpoint($request->getPathInfo());
        $log->setRequestType($request->getMethod());
        $log->setResponseStatus($responseStatus);
        $log->setCreatedAt(new \DateTimeImmutable());
        $log->setUser($user);

        $this->em->persist($log);
        $this->em->flush();
    }
}
