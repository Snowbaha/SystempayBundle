<?php

namespace Snow\SystempayBundle\Twig;

use \Twig_Extension;
use \Twig_SimpleFunction;
use \Twig_Environment;


/**
 * Class TwigExtension
 * @package Snow\SystempayBundle\Twig
 */
class TwigExtension extends Twig_Extension
{
    /**
     * @return array
     */
    public function getFilters()
    {
        return array(
            new Twig_SimpleFunction(
                'systempayForm',
                array($this, 'systempayForm'),
                array(
                    'is_safe' => array('html'),
                    'needs_environment' => true
                )
            ),
        );
    }

    /**
     * @param $fields
     * @return string
     */
    public function systempayForm($fields)
    {
        $twig = new Twig_Environment();
        $form_html = $twig->render('SnowSystempayBundle:::form.html.twig', array('fields' => $fields));

        return $form_html;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'systempay_twig_extension';
    }
}