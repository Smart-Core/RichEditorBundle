<?php

namespace SmartCore\Bundle\RichEditorBundle\Twig\Extension;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Extension\AbstractExtension;

/**
 * Twig Extension for TinyMce support.
 *
 * @author naydav <web@naydav.com>
 */
class TinymceExtension extends AbstractExtension
{
    /**
     * Container.
     *
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Asset Base Url
     * Used to over ride the asset base url (to not use CDN for instance).
     *
     * @var String
     */
    protected $baseUrl;

    /**
     * Initialize tinymce helper.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Gets a service.
     *
     * @param string $id The service identifier
     *
     * @return object The associated service
     */
    public function getService($id)
    {
        return $this->container->get($id);
    }

    /**
     * Get parameters from the service container.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function getParameter($name)
    {
        return $this->container->getParameter($name);
    }

    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return array An array of functions
     */
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('tinymce_init3', [$this, 'tinymce_init3'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * TinyMce initializations.
     *
     * @return string
     */
    public function tinymce_init3(array $options = [])
    {
        $config  = $this->getParameter('smart_rich_editor_tinymce.config');
        $this->baseUrl = (!isset($config['base_url']) ? null : $config['base_url']);

        /** @var $assets \Symfony\Component\Templating\Helper\CoreAssetsHelper */
        $assets = $this->getService('templating.helper.assets');

        // Get path to tinymce script for the jQuery version of the editor
        //$config['jquery_script_url'] = $assets->getUrl($this->baseUrl . 'bundles/stfalcontinymce/vendor/tiny_mce/tiny_mce.jquery.js');
        // @todo вынести в конфиг
        $config['jquery_script_url'] = $assets->getUrl($this->baseUrl.'bundles/felib/tinymce/3/jquery.tinymce.min.js');

        // Get local button's image
        foreach ($config['tinymce_buttons'] as &$customButton) {
            $customButton['image'] = $this->getAssetsUrl($customButton['image']);
        }

        // Update URL to external plugins
        foreach ($config['external_plugins'] as &$extPlugin) {
            $extPlugin['url'] = $this->getAssetsUrl($extPlugin['url']);
        }

        // If the language is not set in the config...
        if (!isset($config['language']) || empty($config['language'])) {
            // get it from the request
            $config['language'] = $this->getService('request_stack')->getCurrentRequest()->getLocale();
        }

        // Check the language code and trim it to 2 symbols (en_US to en, ru_RU to ru, ...)
        if (strlen($config['language']) > 2) {
            $config['language'] = substr($config['language'], 0, 2);
        }

        // TinyMCE does not allow to set different languages to each instance
        foreach ($config['theme'] as $themeName => $themeOptions) {
            $config['theme'][$themeName]['language'] = $config['language'];
        }

        if (isset($config['theme']) && $config['theme']) {
            // Parse the content_css of each theme so we can use 'asset[path/to/asset]' in there
            foreach ($config['theme'] as $themeName => $themeOptions) {
                if (isset($themeOptions['content_css'])) {
                    // As there may be multiple CSS Files specified we need to parse each of them individually
                    $cssFiles = explode(',', $themeOptions['content_css']);

                    foreach ($cssFiles as $idx => $file) {
                        $cssFiles[$idx] = $this->getAssetsUrl(trim($file)); // we trim to be sure we get the file without spaces.
                    }

                    // After parsing we add them together again.
                    $config['theme'][$themeName]['content_css'] = implode(',', $cssFiles);
                }
            }
        }

        return $this->getService('templating')->render('SmartRichEditorBundle:Tinymce:init.html.twig', [
            'tinymce_config' => json_encode($config),
            'include_jquery' => $config['include_jquery'],
            'tinymce_jquery' => $config['tinymce_jquery'],
            'base_url'       => $this->baseUrl,
        ]);
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'smart_rich_editor_tinymce';
    }

    /**
     * Get url from config string.
     *
     * @param string $inputUrl
     *
     * @return string
     */
    protected function getAssetsUrl($inputUrl)
    {
        /** @var $assets \Symfony\Component\Templating\Helper\CoreAssetsHelper */
        $assets = $this->getService('templating.helper.assets');

        $url = preg_replace('/^asset\[(.+)\]$/i', '$1', $inputUrl);

        if ($inputUrl !== $url) {
            return $assets->getUrl($this->baseUrl.$url);
        }

        return $inputUrl;
    }
}
