<?php

namespace Crm\UsersModule\Models\Email;

class StaticDomainFileValidator implements ValidatorInterface
{
    private $file;

    private $blockedDomains = false;

    public function __construct($file = false)
    {
        if (!$file) {
            $file = __DIR__ . '/blocked_domains.txt';
        }
        $this->file = $file;
    }

    public function isValid($email): bool
    {
        $parts = explode('@', $email);
        if (count($parts) == 2) {
            return !$this->isBlockedDomain($parts[1]);
        }
        return true;
    }

    private function isBlockedDomain($domain)
    {
        return in_array($domain, $this->domains(), true);
    }

    private function domains()
    {
        if ($this->blockedDomains === false) {
            $content = file_get_contents($this->file);
            $this->blockedDomains = explode("\n", $content);
        }
        return $this->blockedDomains;
    }
}
