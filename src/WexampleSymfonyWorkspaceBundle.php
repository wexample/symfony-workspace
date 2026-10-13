<?php

namespace Wexample\SymfonyWorkspace;

use Wexample\SymfonyHelpers\Class\AbstractBundle;
use Wexample\SymfonyHelpers\Helper\BundleHelper;
use Wexample\SymfonyHelpers\Interface\LoaderBundleInterface;

/**
 * Screens made of regions side by side — a rail, a work, an inspector, a
 * wall of tiles — and what ties them: a region choosing something, the
 * others showing it. Front-end only: the bundle exists so that the loader
 * finds its assets.
 */
class WexampleSymfonyWorkspaceBundle extends AbstractBundle implements LoaderBundleInterface
{
    public static function getLoaderFrontPaths(): array
    {
        return [
            BundleHelper::getBundleCssAlias(static::class) => __DIR__.'/../assets/',
        ];
    }
}
