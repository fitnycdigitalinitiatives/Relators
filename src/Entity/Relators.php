<?php
namespace Relators\Entity;

use Omeka\Entity\AbstractEntity;
use Omeka\Entity\Property;
use Omeka\Entity\Value;
use Omeka\Entity\Resource;

/**
 * Defines the available relators.
 *
 * @Entity
 */
class Relators extends AbstractEntity
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
     * @ManyToOne(
     *     targetEntity="Omeka\Entity\Resource"
     * )
     * @JoinColumn(
     *     nullable=false,
     *     onDelete="CASCADE"
     * )
     */
    protected $resource;

    /**
     * @ManyToOne(
     *     targetEntity="Omeka\Entity\Property"
     * )
     * @JoinColumn(
     *     nullable=false,
     *     onDelete="CASCADE"
     * )
     */
    protected $property;

    /**
     * @Column(type="text", nullable=true)
     */
    protected $valueMatch;

    /**
     * @Column(type="text", nullable=true)
     */
    protected $uriMatch;

    /**
     * @ManyToOne(targetEntity="Omeka\Entity\Resource")
     * @JoinColumn(onDelete="CASCADE")
     */
    protected $valueResourceMatch;

    /**
     * @Column(name="`values`", type="json_array", nullable=true)
     */
    protected $values;

    public function getId()
    {
        return $this->id;
    }

    public function setResource(Resource $resource)
    {
        $this->resource = $resource;
    }

    public function getResource()
    {
        return $this->resource;
    }

    public function setProperty(Property $property)
    {
        $this->property = $property;
    }

    public function getProperty()
    {
        return $this->property;
    }

    public function setValues($values)
    {
        $this->values = $values;
    }

    public function getValues()
    {
        return $this->values;
    }

    public function setValueMatch($valueMatch)
    {
        $this->valueMatch = $valueMatch;
    }

    public function getValueMatch()
    {
        return $this->valueMatch;
    }

    public function setUriMatch($uriMatch)
    {
        $this->uriMatch = $uriMatch;
    }

    public function getUriMatch()
    {
        return $this->uriMatch;
    }

    public function setValueResourceMatch(Resource $valueResourceMatch = null)
    {
        $this->valueResourceMatch = $valueResourceMatch;
    }

    public function getValueResourceMatch()
    {
        return $this->valueResourceMatch;
    }
}
