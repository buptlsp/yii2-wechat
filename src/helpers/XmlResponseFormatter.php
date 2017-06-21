<?php
namespace lspbupt\wechat\helpers;

use DOMElement;
use DOMText;
use yii\helpers\StringHelper;
use yii\base\Arrayable;
use DOMCdataSection;

class XmlResponseFormatter extends \yii\web\XmlResponseFormatter{
    public $rootTag = "xml";  // 这里我就可以把 rootTag 的默认值修改成 xml 了
    
    /**
     * 如果需要使用 CDATA 那就需要把原来的数据转成数组，并且数组含有以下key
     * ，我们就把这个节点添加成一个 DOMCdataSection
     */
    const CDATA = '---cdata---';  // 这个是是否使用CDATA 的下标
    
     /**
     * @param DOMElement $element
     * @param mixed $data
     */
    protected function buildXml($element, $data)
    {
        if (is_array($data) ||
            ($data instanceof \Traversable && $this->useTraversableAsArray && !$data instanceof Arrayable)
        ) {
            foreach ($data as $name => $value) {
                if (is_int($name) && is_object($value)) {
                    $this->buildXml($element, $value);
                } elseif (is_array($value) || is_object($value)) {
                    $child = new DOMElement($this->getValidXmlElementName($name));
                    $element->appendChild($child);
                    // 主要就是修改这一个点，如果值是一个数组，并且含有 CDATA 的，那么就直接创建一个 CdataSection 节点，
                    // 而不把它本身当作列表再回调。
                    if(array_key_exists(self::CDATA, $value)){
                        $child->appendChild(new DOMCdataSection((string) $value[0]));
                    }else{
                        $this->buildXml($child, $value);
                    }
                } else {
                    $child = new DOMElement($this->getValidXmlElementName($name));
                    $element->appendChild($child);
                    $child->appendChild(new DOMText($this->formatScalarValue($value)));
                }
            }
        } elseif (is_object($data)) {
            if ($this->useObjectTags) {
                $child = new DOMElement(StringHelper::basename(get_class($data)));
                $element->appendChild($child);
            } else {
                $child = $element;
            }
            if ($data instanceof Arrayable) {
                $this->buildXml($child, $data->toArray());
            } else {
                $array = [];
                foreach ($data as $name => $value) {
                    $array[$name] = $value;
                }
                $this->buildXml($child, $array);
            }
        } else {
            $element->appendChild(new DOMText($this->formatScalarValue($data)));
        }



        /*if (is_object($data)) {
            // 这里保持原来的代码不变
        } elseif (is_array($data)) {
            foreach ($data as $name => $value) {
                if (is_int($name) && is_object($value)) {
                    $this->buildXml($element, $value);
                } elseif (is_array($value) || is_object($value)) {
                    $child = new DOMElement(is_int($name) ? $this->itemTag : $name);
                    $element->appendChild($child);
                    // 主要就是修改这一个点，如果值是一个数组，并且含有 CDATA 的，那么就直接创建一个 CdataSection 节点，
                    // 而不把它本身当作列表再回调。
                    if(array_key_exists(self::CDATA, $value)){
                        $child->appendChild(new DOMCdataSection((string) $value[0]));
                    }else{
                        $this->buildXml($child, $value);
                    }
                } else {
                    $child = new DOMElement(is_int($name) ? $this->itemTag : $name);
                    $element->appendChild($child);
                    $child->appendChild(new DOMText((string) $value));
                }
            }
        } else {
            $element->appendChild(new DOMText((string) $data));
        }*/
    }
}
