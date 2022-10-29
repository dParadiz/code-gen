<?php

namespace Dparadiz\Codegen;

use Mustache_Engine;
use Mustache_LambdaHelper;

final class RendererFactory
{
    public static function getRenderer(string $templatePath, string $namespace = ''): Renderer
    {
        if (!empty($namespace) && substr_compare($namespace, '\\', -1, 1) !== 0) {
            $namespace .= '\\';
        }

        $m = new Mustache_Engine([
            'entity_flags' => ENT_QUOTES,
            'helpers' => [
                'ucfirst' => fn(string $text, Mustache_LambdaHelper $helper) => ucfirst($helper->render($text)),
                'rTrim' => fn(string $text, Mustache_LambdaHelper $helper) => preg_replace('/(,|\||\n) ?$/', '', $helper->render($text)),
                'phpOpenTag' => '<?php',
                'baseNamespace' => $namespace,
                'serializeProperty' => fn(string $text, Mustache_LambdaHelper $helper) => '{{> partials/property-serialize/' . $helper->render($text) . '}}',
                'deserializeProperty' => fn(string $text, Mustache_LambdaHelper $helper) => '{{> partials/property-deserialize/' . $helper->render($text) . '}}',
            ],
        ]);
        $m->setLoader(new \Mustache_Loader_FilesystemLoader($templatePath));

        return new Renderer($m);
    }

}
