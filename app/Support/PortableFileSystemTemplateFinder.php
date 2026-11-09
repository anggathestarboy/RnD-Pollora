<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\View\FileViewFinder;
use Pollora\View\Infrastructure\Services\FileSystemTemplateFinder;

/**
 * Template finder that understands Windows and Unix path separators.
 *
 * The upstream implementation compares the theme path with paths handed over
 * by WordPress using plain string functions. On Windows those paths mix
 * backslashes and forward slashes (and may differ in drive-letter case), so
 * the theme prefix is never stripped and the resulting "view name" is the
 * whole absolute path. That name never exists in the view factory, which makes
 * the frontend controller fall back to including the raw Blade file.
 */
class PortableFileSystemTemplateFinder extends FileSystemTemplateFinder
{
    /**
     * Get view name from a template file path.
     */
    public function getViewNameFromPath(string $filePath): ?string
    {
        $themePath = $this->normalizePath($this->getThemePath());
        $viewName = $this->normalizePath($filePath);

        // Remove the theme base path to get a theme-relative path.
        if ($themePath !== '' && $themePath !== '0') {
            $viewName = $this->stripPrefix($viewName, $themePath) ?? str_replace($themePath, '', $viewName);
        }

        $viewName = trim($viewName, '/');

        // Remove the directory prefixes registered as view paths.
        /** @var FileViewFinder $finder */
        $finder = $this->finder;

        foreach ($finder->getPaths() as $viewPath) {
            $viewPath = $this->normalizePath($viewPath);

            $relativePath = $themePath !== '' && $themePath !== '0'
                ? ($this->stripPrefix($viewPath, $themePath) ?? $viewPath)
                : $viewPath;

            $relativePath = trim($relativePath, '/');

            if ($relativePath === '') {
                continue;
            }

            if ($viewName === $relativePath || $this->hasPrefix($viewName, $relativePath.'/')) {
                $viewName = ltrim(substr($viewName, strlen($relativePath)), '/');
                break;
            }
        }

        // Convert path separators to dots and remove the extension.
        $viewName = str_replace('/', '.', $viewName);
        $viewName = preg_replace('/\.(blade\.)?php$/', '', $viewName);

        return in_array($viewName, ['', '0'], true) ? null : $viewName;
    }

    /**
     * Normalize a filesystem path to forward slashes.
     */
    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    /**
     * Strip a prefix from a path, returning null when it is not present.
     */
    private function stripPrefix(string $path, string $prefix): ?string
    {
        if ($prefix === '' || ! $this->hasPrefix($path, $prefix)) {
            return null;
        }

        return substr($path, strlen($prefix));
    }

    /**
     * Determine whether a path starts with a prefix.
     */
    private function hasPrefix(string $path, string $prefix): bool
    {
        if ($prefix === '') {
            return false;
        }

        // Windows paths are case-insensitive, and the drive letter can be
        // reported with either case depending on where it came from.
        if (DIRECTORY_SEPARATOR === '\\') {
            return strncasecmp($path, $prefix, strlen($prefix)) === 0;
        }

        return str_starts_with($path, $prefix);
    }
}
