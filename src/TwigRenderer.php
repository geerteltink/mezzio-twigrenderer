<?php

/**
 * @see       https://github.com/mezzio/mezzio-twigrenderer for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio-twigrenderer/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio-twigrenderer/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Mezzio\Twig;

use LogicException;
use Mezzio\Template\ArrayParametersTrait;
use Mezzio\Template\DefaultParamsTrait;
use Mezzio\Template\TemplatePath;
use Mezzio\Template\TemplateRendererInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;

use function is_string;
use function preg_match;
use function preg_replace;
use function sprintf;

/**
 * Template implementation bridging twig/twig
 */
class TwigRenderer implements TemplateRendererInterface
{
    use ArrayParametersTrait;
    use DefaultParamsTrait;

    /**
     * @var string
     */
    private $suffix;

    /**
     * @var FilesystemLoader
     */
    protected $twigLoader;

    /**
     * @var Environment
     */
    protected $template;

    public function __construct(Environment $template = null, string $suffix = 'html')
    {
        if (null === $template) {
            $template = $this->createTemplate($this->getDefaultLoader());
        }

        try {
            $loader = $template->getLoader();
        } catch (LogicException $e) {
            $loader = $this->getDefaultLoader();
            $template->setLoader($loader);
        }

        $this->template   = $template;
        $this->twigLoader = $loader;
        $this->suffix     = is_string($suffix) ? $suffix : 'html';
    }

    /**
     * Create a default Twig environment
     *
     * @param FilesystemLoader $loader
     *
     * @return Environment
     */
    private function createTemplate(FilesystemLoader $loader) : Environment
    {
        return new Environment($loader);
    }

    /**
     * Get the default loader for template
     */
    private function getDefaultLoader() : FilesystemLoader
    {
        return new FilesystemLoader();
    }

    /**
     * Render
     *
     * @param string       $name
     * @param array|object $params
     *
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function render(string $name, $params = []) : string
    {
        // Merge parameters based on requested template name
        $params = $this->mergeParams($name, $this->normalizeParams($params));

        $name   = $this->normalizeTemplate($name);

        // Merge parameters based on normalized template name
        $params = $this->mergeParams($name, $params);

        return $this->template->render($name, $params);
    }

    /**
     * Add a path for template
     *
     * @param string      $path
     * @param string|null $namespace
     *
     * @throws LoaderError
     */
    public function addPath(string $path, string $namespace = null) : void
    {
        $namespace = $namespace ?: FilesystemLoader::MAIN_NAMESPACE;
        $this->twigLoader->addPath($path, $namespace);
    }

    /**
     * Get the template directories
     *
     * @return TemplatePath[]
     */
    public function getPaths() : array
    {
        $paths = [];
        foreach ($this->twigLoader->getNamespaces() as $namespace) {
            $name = ($namespace !== FilesystemLoader::MAIN_NAMESPACE) ? $namespace : null;

            foreach ($this->twigLoader->getPaths($namespace) as $path) {
                $paths[] = new TemplatePath($path, $name);
            }
        }
        return $paths;
    }

    /**
     * Normalize namespaced template.
     *
     * Normalizes templates in the format "namespace::template" to
     * "@namespace/template".
     *
     * @param string $template
     *
     * @return string
     */
    public function normalizeTemplate(string $template) : string
    {
        $template = preg_replace('#^([^:]+)::(.*)$#', '@$1/$2', $template);
        if (! preg_match('#\.[a-z]+$#i', $template)) {
            return sprintf('%s.%s', $template, $this->suffix);
        }

        return $template;
    }
}
