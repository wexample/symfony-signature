<?php

namespace Wexample\SymfonySignature\Interface;

/**
 * The account of the application an address belongs to, if any: a signer
 * added by its address signs signed in when it has one, through a signed
 * link otherwise. One per application; without it, every signer added by
 * address signs from outside.
 */
interface SignerAccountResolverInterface
{
    public function findAccount(string $email): ?object;

    /**
     * The address and the name an account signs as.
     *
     * @return array{string, string}|null
     */
    public function describe(object $account): ?array;
}
