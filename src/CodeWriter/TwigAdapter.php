<?php declare(strict_types=1);

namespace Dparadiz\Codegen\CodeWriter;

use Dparadiz\Codegen\CodeWriterInterface;
use Twig;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

readonly class TwigAdapter implements CodeWriterInterface
{
    private Twig\Environment $twig;

    public function __construct(string $templatePath)
    {
        $loader = new Twig\Loader\FilesystemLoader([
            $templatePath,
        ]);
        $this->twig = new Twig\Environment($loader);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function generate(string $template, object|array $data): string
    {
        return $this->twig->render($template.'.twig', $data);
    }
}