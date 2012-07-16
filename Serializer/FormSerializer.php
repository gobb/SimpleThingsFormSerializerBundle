<?php
/**
 * SimpleThings FormSerializerBundle
 *
 * LICENSE
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file LICENSE.txt.
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to kontakt@beberlei.de so I can send you a copy immediately.
 */

namespace SimpleThings\FormSerializerBundle\Serializer;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

use SimpleThings\FormSerializerBundle\Serializer\NamingStrategy\CamelCaseStrategy;
use SimpleThings\FormSerializerBundle\Serializer\NamingStrategy\NamingStrategy;

class FormSerializer
{
    private $factory;
    private $encoder;
    private $namingStrategy;

    public function __construct(FormFactoryInterface $factory, EncoderInterface $encoder, NamingStrategy $namingStrategy = null)
    {
        $this->factory        = $factory;
        $this->encoder        = $encoder;
        $this->namingStrategy = $namingStrategy ?: new CamelCaseStrategy();
    }

    public function serialize($object, $typeBuilder, $format)
    {
        if ($typeBuilder instanceof FormTypeInterface) {
            $form = $this->factory->create($typeBuilder, $object);
        } else if ($typeBuilder instanceof FormBuilderInterface) {
            $form = $typeBuilder->getForm();
            $form->setData($object);
        } else {
            throw new UnexpectedTypeException($typeBuilder, 'FormTypeInterface|FormBuilderInterface');
        }

        $options = $form->getConfig()->getOptions();
        $xmlName = isset($options['serialize_xml_name'])
            ? $options['serialize_xml_name']
            : 'entry';

        $data = $this->serializeForm($form, $format == 'xml');

        $this->encoder->getEncoder('xml')->setRootNodeName($xmlName);

        return $this->encoder
                    ->encode($data, $format);
    }

    private function serializeForm($form, $isXml)
    {
        if ( ! $form->hasChildren()) {
            $options = $form->getConfig()->getOptions();
            $value   = $form->getViewData();

            if ( ! isset($options['serialize_collection_form'])) {
                return $value;
            }

            $data = array();
            foreach ($value as $child) {
                $options['serialize_collection_form']->setData($child);
                $data[] = $this->serializeForm($options['serialize_collection_form'], $isXml);
            }

            return $data;
        }

        $data = array();

        foreach ($form->getChildren() as $child) {
            $options = $child->getConfig()->getOptions();
            $name    = $this->namingStrategy->translateName($child);
            $name    = $isXml && $options['serialize_attribute'] ? '@' . $name : $name;

            if ($options['compound'] && ! $options['serialize_inline']) {
                $data[$name][$options['serialize_xml_name']] = $this->serializeForm($child, $isXml);
            } else {
                $data[$name] = $this->serializeForm($child, $isXml);
            }
        }

        return $data;
    }
}

