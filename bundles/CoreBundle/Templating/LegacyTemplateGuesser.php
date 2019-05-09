<?php

namespace Pimcore\Bundle\CoreBundle\Templating;

use Psr\Container\ContainerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Templating\TemplateGuesser as BaseTemplateGuesser;
use Symfony\Component\HttpKernel\KernelInterface;
use Doctrine\Common\Persistence\Proxy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Templating\TemplateReference;

/**
 * Provides backward compatibility for camelCase template names and PHP engine support
 *
 */
class LegacyTemplateGuesser extends BaseTemplateGuesser
{
    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var $container
     */
    private $container;

    /**
     * @var string[]
     */
    private $controllerPatterns;

    public function __construct(KernelInterface $kernel, ContainerInterface $container, $controllerPatterns = [])
    {
        $controllerPatterns[] = '/Controller\\\(.+)Controller$/';

        $this->kernel = $kernel;
        $this->controllerPatterns = $controllerPatterns;
        $this->container = $container;

        parent::__construct($kernel, $controllerPatterns);
    }

    /**
     * @inheritdoc
     */
    public function guessTemplateName($controller, Request $request, $engine = 'php')
    {
        //first lookup for new template name(snake_case)
        //if not found then use legacy guesser template name(camelCase)
        $templateReference = parent::guessTemplateName($controller, $request);

        // Only AppBundle should use templates inside app folder
        if (0 === strpos($templateReference,'@') && substr(explode('/',$templateReference)[0],1) === 'App') {
            $templateReference = str_replace('@App/','',$templateReference);
        }

        //update view file format(not supported by Sensio), if engine is php
        if($engine == 'php') {
            $templateReference = str_replace('.twig','.php',$templateReference);
        }

        if($this->container->get('templating')->exists($templateReference)) {
            return $templateReference;
        }

        if (is_object($controller) && method_exists($controller, '__invoke')) {
            $controller = array($controller, '__invoke');
        } elseif (!is_array($controller)) {
            throw new \InvalidArgumentException(sprintf('First argument of %s must be an array callable or an object defining the magic method __invoke. "%s" given.', __METHOD__, gettype($controller)));
        }
        $className = $this->getRealClass(\get_class($controller[0]));

        $matchController = null;
        foreach ($this->controllerPatterns as $pattern) {
            if (preg_match($pattern, $className, $tempMatch)) {
                $matchController = $tempMatch;
                break;
            }
        }
        if (null === $matchController) {
            throw new \InvalidArgumentException(sprintf('The "%s" class does not look like a controller class (its FQN must match one of the following regexps: "%s")', \get_class($controller[0]), implode('", "', $this->controllerPatterns)));
        }

        if ($controller[1] === '__invoke') {
            $matchAction = $matchController;
            $matchController = null;
        } elseif (!preg_match('/^(.+)Action$/', $controller[1], $matchAction)) {
            $matchAction = array(null, $controller[1]);
        }

        $bundle = $this->getBundleForClass($className);
        if ($bundle) {
            while ($bundleName = $bundle->getName()) {
                if (null === $parentBundleName = $bundle->getParent()) {
                    $bundleName = $bundle->getName();
                    break;
                }
                $bundles = $this->kernel->getBundle($parentBundleName, false);
                $bundle = array_pop($bundles);
            }
        } else {
            $bundleName = null;
        }

        $legacyTemplateReference = new TemplateReference($bundleName, $matchController[1], $matchAction[1], $request->getRequestFormat(), $engine);

        // Only AppBundle should use templates inside app folder
        if ($legacyTemplateReference->get('bundle') === 'AppBundle') {
            $legacyTemplateReference->set('bundle', '');
        }

        if(!$this->container->get('templating')->exists($legacyTemplateReference->getLogicalName())) {
            throw new \InvalidArgumentException(sprintf('The template "%s" and fallback: "%s" does not exist.',$templateReference, $legacyTemplateReference));
        }

        return $legacyTemplateReference;
    }

    /**
     * @inheritdoc
     */
    protected function getBundleForClass($class)
    {
        $reflectionClass = new \ReflectionClass($class);
        $bundles = $this->kernel->getBundles();
        do {
            $namespace = $reflectionClass->getNamespaceName();
            foreach ($bundles as $bundle) {
                if (0 === strpos($namespace, $bundle->getNamespace())) {
                    return $bundle;
                }
            }
            $reflectionClass = $reflectionClass->getParentClass();
        } while ($reflectionClass);
    }

    private static function getRealClass(string $class): string
    {
        if (false === $pos = strrpos($class, '\\'.Proxy::MARKER.'\\')) {
            return $class;
        }

        return substr($class, $pos + Proxy::MARKER_LENGTH + 2);
    }
}
