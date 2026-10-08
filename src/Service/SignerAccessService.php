<?php

namespace Wexample\SymfonySignature\Service;

use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Wexample\SymfonySignature\Entity\EnvelopeSigner;
use Wexample\SymfonySignature\Routing\SignatureRoute;

/**
 * The link a signer signs through. An outside signer has no account: the
 * link is signed, and works until the envelope expires — or `link_lifetime`
 * after it was sealed. An account signer signs signed in, and its link is
 * the plain page.
 */
class SignerAccessService
{
    public const string PARAMETER_SIGNER = 'signer';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UriSigner $uriSigner,
        #[Autowire(param: 'wexample_symfony_signature.link_lifetime')]
        private readonly int $linkLifetime,
    ) {
    }

    /**
     * Null where no page answers it.
     */
    public function getUrl(EnvelopeSigner $signer): ?string
    {
        if (! $this->urlGenerator->getRouteCollection()->get(SignatureRoute::SIGN)) {
            return null;
        }

        $url = $this->urlGenerator->generate(SignatureRoute::SIGN, [
            self::PARAMETER_SIGNER => (string) $signer->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        if (! $signer->isOutside()) {
            return $url;
        }

        $envelope = $signer->getEnvelope();
        $expiresAt = $envelope->getExpiresAt()
            ?? ($envelope->getDateSealed() ?? new DateTimeImmutable())->modify('+' . $this->linkLifetime . ' seconds');

        return $this->uriSigner->sign($url, $expiresAt);
    }

    /**
     * Whether $request is a signed link of an outside signer, still valid.
     */
    public function isValidLink(Request $request): bool
    {
        return $this->uriSigner->checkRequest($request);
    }
}
