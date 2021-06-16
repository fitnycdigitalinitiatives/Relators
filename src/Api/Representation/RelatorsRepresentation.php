<?php
namespace Relators\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

class RelatorsRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLdType()
    {
        return 'o-module-relators:Relators';
    }

    public function getJsonLd()
    {
        return [
            'o:resource' => $this->resource()->getReference(),
            'o:property' => $this->property()->getReference(),
            'o-module-relators:values' => $this->values(),
            'o-module-relators:valueMatch' => $this->valueMatch(),
            'o-module-relators:uriMatch' => $this->uriMatch(),
            'o-module-relators:valueResourceMatch' => $this->valueResourceMatch() ? $this->valueResourceMatch()->getReference() : null,
        ];
    }

    public function resource()
    {
        return $this->getAdapter('resources')
            ->getRepresentation($this->resource->getResource());
    }

    public function property()
    {
        return $this->getAdapter('properties')
            ->getRepresentation($this->resource->getProperty());
    }

    public function values()
    {
        return $this->resource->getValues();
    }

    public function valueMatch()
    {
        return $this->resource->getvalueMatch();
    }

    public function uriMatch()
    {
        return $this->resource->getUriMatch();
    }

    public function valueResourceMatch()
    {
        $resource = $this->resource->getValueResourceMatch();
        if (!$resource) {
            return null;
        }
        $resourceAdapter = $this->getAdapter($resource->getResourceName());
        return $resourceAdapter->getRepresentation($resource);
    }
}
