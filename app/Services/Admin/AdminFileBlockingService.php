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

        if ($this->containsBlockedTermInName($path, $terms)) {
            throw new HttpForbiddenException('Access to this file is blocked by admin policy.');
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
    public function enforceForWrite(User $user, string $content, ?string $path = null): void
    {
        if (!$this->permissions->shouldEnforceFileBlocking($user)) {
            return;
        }

        $terms = $this->permissions->getFileBlockingTerms();
        if (empty($terms)) {
            return;
        }

        if ($path !== null && $this->containsBlockedTermInName($path, $terms)) {
            throw new HttpForbiddenException('Writing this file is blocked by admin policy.');
        }

        if ($this->containsBlockedTerm($content, $terms)) {
            throw new HttpForbiddenException('Writing this file is blocked by admin policy.');
        }
    }

    /**
     * Enforce file blocking based on the filename.
     *
     * @throws HttpForbiddenException
     */
    public function enforceForName(User $user, string $path): void
    {
        if (!$this->permissions->shouldEnforceFileBlocking($user)) {
            return;
        }

        $terms = $this->permissions->getFileBlockingTerms();
        if (empty($terms)) {
            return;
        }

        if ($this->containsBlockedTermInName($path, $terms)) {
            throw new HttpForbiddenException('This file name is blocked by admin policy.');
        }
    }

    /**
     * Scan a directory and delete any files matching blocked terms in name or content.
     */
    public function scanAndDeleteBlockedFiles(
        User $user,
        Server $server,
        string $directory,
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

        $contents = $fileRepository->setServer($server)->getDirectory($directory);
        foreach ($contents as $item) {
            $name = $item['name'] ?? null;
            if (!$name) {
                continue;
            }

            $isFile = $item['file'] ?? true;
            $isSymlink = $item['symlink'] ?? false;

            $path = rtrim($directory, '/') . '/' . ltrim($name, '/');

            if (!$isFile) {
                $this->scanAndDeleteBlockedFiles($user, $server, $path, $fileRepository, $maxBytes);
                continue;
            }

            if ($this->containsBlockedTermInName($name, $terms)) {
                $fileRepository->setServer($server)->deleteFiles($directory, [$name]);
                continue;
            }

            if ($isSymlink) {
                continue;
            }

            try {
                $content = $fileRepository->setServer($server)->getContent($path, $maxBytes);
            } catch (FileSizeTooLargeException $exception) {
                // Be strict: remove files we cannot scan safely.
                $fileRepository->setServer($server)->deleteFiles($directory, [$name]);
                continue;
            }

            if ($this->containsBlockedTerm($content, $terms)) {
                $fileRepository->setServer($server)->deleteFiles($directory, [$name]);
            }
        }
    }

    /**
     * Determine if the filename contains any blocked term.
     *
     * @param string[] $terms
     */
    protected function containsBlockedTermInName(string $path, array $terms): bool
    {
        $name = basename($path);

        foreach ($terms as $term) {
            if ($term !== '' && stripos($name, $term) !== false) {
                return true;
            }
        }

        return false;
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
