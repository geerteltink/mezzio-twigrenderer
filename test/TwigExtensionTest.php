<?php

/**
 * @see       https://github.com/mezzio/mezzio-twigrenderer for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio-twigrenderer/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio-twigrenderer/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace MezzioTest\Twig;

use Mezzio\Helper\ServerUrlHelper;
use Mezzio\Helper\UrlHelper;
use Mezzio\Twig\TwigExtension;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Twig\TwigFunction;

use function sprintf;

/**
 * phpcs:disable WebimpressCodingStandard.Functions.Param.MissingSpecification
 */
class TwigExtensionTest extends TestCase
{
    /** @var ServerUrlHelper|MockObject */
    private $serverUrlHelper;

    /** @var UrlHelper|MockObject */
    private $urlHelper;

    protected function setUp(): void
    {
        $this->serverUrlHelper = $this->createMock(ServerUrlHelper::class);
        $this->urlHelper       = $this->createMock(UrlHelper::class);
    }

    public function createExtension($assetsUrl, $assetsVersion): TwigExtension
    {
        return new TwigExtension(
            $this->serverUrlHelper,
            $this->urlHelper,
            $assetsUrl,
            $assetsVersion
        );
    }

    /** @return object|bool */
    public function findFunction($name, array $functions)
    {
        foreach ($functions as $function) {
            $this->assertInstanceOf(TwigFunction::class, $function);
            if ($function->getName() === $name) {
                return $function;
            }
        }

        return false;
    }

    public function assertFunctionExists($name, array $functions, $message = null)
    {
        $message  = $message ?: sprintf('Failed to identify function by name %s', $name);
        $function = $this->findFunction($name, $functions);
        $this->assertInstanceOf(TwigFunction::class, $function, $message);
    }

    public function testRegistersTwigFunctions()
    {
        $extension = $this->createExtension('', '');
        $functions = $extension->getFunctions();
        $this->assertFunctionExists('path', $functions);
        $this->assertFunctionExists('url', $functions);
        $this->assertFunctionExists('absolute_url', $functions);
        $this->assertFunctionExists('asset', $functions);
    }

    public function testMapsTwigFunctionsToExpectedMethods()
    {
        $extension = $this->createExtension('', '');
        $functions = $extension->getFunctions();
        $this->assertSame(
            [$extension, 'renderUri'],
            $this->findFunction('path', $functions)->getCallable(),
            'Received different path function than expected'
        );
        $this->assertSame(
            [$extension, 'renderUrl'],
            $this->findFunction('url', $functions)->getCallable(),
            'Received different url function than expected'
        );
        $this->assertSame(
            [$extension, 'renderUrlFromPath'],
            $this->findFunction('absolute_url', $functions)->getCallable(),
            'Received different path function than expected'
        );
        $this->assertSame(
            [$extension, 'renderAssetUrl'],
            $this->findFunction('asset', $functions)->getCallable(),
            'Received different asset function than expected'
        );
    }

    public function testRenderUriDelegatesToComposedUrlHelper()
    {
        $this->urlHelper
            ->method('generate')
            ->with('foo', ['id' => 1], [], null, [])
            ->willReturn('URL');
        $extension = $this->createExtension('', '');
        $this->assertSame('URL', $extension->renderUri('foo', ['id' => 1]));
    }

    public function testRenderUrlDelegatesToComposedUrlHelperAndServerUrlHelper()
    {
        $this->urlHelper
            ->method('generate')
            ->with('foo', ['id' => 1], [], null, [])
            ->willReturn('PATH');
        $this->serverUrlHelper
            ->method('generate')
            ->with('PATH')
            ->willReturn('HOST/PATH');
        $extension = $this->createExtension('', '');
        $this->assertSame('HOST/PATH', $extension->renderUrl('foo', ['id' => 1]));
    }

    public function testRenderUrlFromPathDelegatesToComposedServerUrlHelper()
    {
        $this->serverUrlHelper
            ->method('generate')
            ->with('PATH')
            ->willReturn('HOST/PATH');
        $extension = $this->createExtension('', '');
        $this->assertSame('HOST/PATH', $extension->renderUrlFromPath('PATH'));
    }

    public function testRenderAssetUrlUsesComposedAssetUrlAndVersionToGenerateUrl()
    {
        $extension = $this->createExtension('https://images.example.com/', 'XYZ');
        $this->assertSame('https://images.example.com/foo.png?v=XYZ', $extension->renderAssetUrl('foo.png'));
    }

    public function testRenderAssetUrlUsesProvidedVersionToGenerateUrl()
    {
        $extension = $this->createExtension('https://images.example.com/', 'XYZ');
        $this->assertSame(
            'https://images.example.com/foo.png?v=ABC',
            $extension->renderAssetUrl('foo.png', 'ABC')
        );
    }

    public function emptyAssetVersions(): array
    {
        return [
            'null'         => [null],
            'empty-string' => [''],
        ];
    }

    /**
     * @dataProvider emptyAssetVersions
     * @param null|string $emptyValue
     */
    public function testRenderAssetUrlWithoutProvidedVersion($emptyValue)
    {
        $extension = $this->createExtension('https://images.example.com/', $emptyValue);
        $this->assertSame(
            'https://images.example.com/foo.png',
            $extension->renderAssetUrl('foo.png')
        );
    }

    public function zeroAssetVersions(): array
    {
        return [
            'zero'        => [0],
            'zero-string' => ['0'],
        ];
    }

    /**
     * @dataProvider zeroAssetVersions
     * @param int|string $zeroValue
     */
    public function testRendersZeroVersionAssetUrl($zeroValue)
    {
        $extension = $this->createExtension('https://images.example.com/', $zeroValue);
        $this->assertSame(
            'https://images.example.com/foo.png?v=0',
            $extension->renderAssetUrl('foo.png')
        );
    }
}
