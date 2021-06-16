<?php
namespace Relators\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class RelatorsAdapter extends AbstractEntityAdapter
{
    public function getResourceName()
    {
        return 'relators';
    }

    public function getRepresentationClass()
    {
        return 'Relators\Api\Representation\RelatorsRepresentation';
    }

    public function getEntityClass()
    {
        return 'Relators\Entity\Relators';
    }

    public function hydrate(
        Request $request,
        EntityInterface $entity,
        ErrorStore $errorStore
    ) {
        $data = $request->getContent();
        if (Request::CREATE === $request->getOperation()
            && isset($data['o:resource']['o:id'])
        ) {
            $resource = $this->getAdapter('resources')->findEntity($data['o:resource']['o:id']);
            $entity->setResource($resource);
        }
        if (Request::CREATE === $request->getOperation()
            && isset($data['o:property']['o:id'])
        ) {
            $property = $this->getAdapter('properties')->findEntity($data['o:property']['o:id']);
            $entity->setProperty($property);
        }
        if ($this->shouldHydrate($request, 'o-module-relators:values')) {
            $entity->setValues($request->getValue('o-module-relators:values'));
        }
    }

    public function validateEntity(EntityInterface $entity, ErrorStore $errorStore)
    {
        if (!$entity->getResource()) {
            $errorStore->addError('o:resource', 'An relator must have an resource.'); // @translate
        }
        // add data validation or something
    }

    public function buildQuery(QueryBuilder $qb, array $query)
    {
        if (isset($query['resource_id'])) {
            $resources = $query['resource_id'];
            if (!is_array($resources)) {
                $resources = [$resources];
            }
            $resources = array_filter($resources, 'is_numeric');

            if ($resources) {
                $resourceAlias = $this->createAlias();
                $qb->innerJoin(
                    'omeka_root.resource',
                    $resourceAlias,
                    'WITH',
                    $qb->expr()->in("$resourceAlias.id", $this->createNamedParameter($qb, $resources))
                );
            }
        }
    }
}
