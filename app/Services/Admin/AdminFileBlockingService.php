<?php

namespace Pterodactyl\Services\Admin;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Exceptions\Http\HttpForbiddenException;
use Pterodactyl\Exceptions\Http\Server\FileSizeTooLargeException;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;

class AdminFileBlockingService
{
    public function __construct(private AdminPermissionService $permissions)
    {
    }

    public function shouldEnforce(User $user): bool
    {
        return $this->permissions->shouldEnforceFileBlocking($user);
    }

    /**
     * Enforce file blocking based on the admin's configuration.
     *
     * @throws HttpForbiddenException
     */
    public function enforceForContent(
        User $user,
        Server $server,
        string $path,
        DaemonFileRepository $fileRepository,
        ?int $maxBytes = null,
    ): void {
        if (!$this->permissions->shouldEnforceFileBlocking($user)) {
            return;
        }

        $terms = $this->permissions->getFileBlockingTerms();
        if (empty($terms)) {
            return;
        }

        try {
            $content = $fileRepository->setServer($server)->getContent($path, $maxBytes);
        } catch (FileSizeTooLargeException $exception) {
            throw new HttpForbiddenException('File is too large to scan due to admin file blocking.');
        }

        if ($this->containsBlockedTerm($content, $terms)) {
            throw new HttpForbiddenException('Access to this file is blocked by admin policy.');
        }
    }

    /**
     * Enforce file blocking based on the content being written.
     *
     * @throws HttpForbiddenException
     */
    public function enforceForWrite(User $user, string $content): void
    {
        if (!$this->permissions->shouldEnforceFileBlocking($user)) {
            return;
        }

        $terms = $this->permissions->getFileBlockingTerms();
        if (empty($terms)) {
            return;
        }

        if ($this->containsBlockedTerm($content, $terms)) {
            throw new HttpForbiddenException('Writing this file is blocked by admin policy.');
        }
    }

    /**
     * Determine if the content contains any blocked term.
     *
     * @param string[] $terms
     */
    protected function containsBlockedTerm(string $content, array $terms): bool
    {
        foreach ($terms as $term) {
            if ($term !== '' && stripos($content, $term) !== false) {
                return true;
            }
        }

        return false;
    }
}
