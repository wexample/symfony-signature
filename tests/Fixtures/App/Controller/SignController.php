<?php

namespace Wexample\SymfonySignature\Tests\Fixtures\App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Wexample\SymfonySignature\Routing\SignatureRoute;

/**
 * Where the links lead; symfony-signature-ds draws the page.
 */
final class SignController
{
    #[Route(path: '/sign/{signer}', name: SignatureRoute::SIGN)]
    public function __invoke(): Response
    {
        return new Response('sign');
    }
}
