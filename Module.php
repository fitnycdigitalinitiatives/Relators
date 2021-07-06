<?php
namespace Relators;

use Omeka\Module\AbstractModule;
use Omeka\Permissions\Acl;
use Omeka\Entity\Resource;
use Laminas\Form\Fieldset;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceLocatorInterface;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);

        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        $acl->allow(
            [Acl::ROLE_AUTHOR,
                Acl::ROLE_EDITOR,
                Acl::ROLE_GLOBAL_ADMIN,
                Acl::ROLE_REVIEWER,
                Acl::ROLE_SITE_ADMIN,
            ],
            ['Relators\Api\Adapter\RelatorsAdapter', 'Relators\Entity\Relators',]
        );

        $acl->allow(
            null,
            ['Relators\Api\Adapter\RelatorsAdapter', 'Relators\Entity\Relators',],
            ['show', 'browse', 'read', 'search']
        );
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $conn = $serviceLocator->get('Omeka\Connection');
        $conn->exec("CREATE TABLE relators (id INT AUTO_INCREMENT NOT NULL, resource_id INT NOT NULL, property_id INT NOT NULL, value_resource_match_id INT DEFAULT NULL, value_match LONGTEXT DEFAULT NULL, uri_match LONGTEXT DEFAULT NULL, `values` LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json_array)', INDEX IDX_FA6E3B3E89329D25 (resource_id), INDEX IDX_FA6E3B3E549213EC (property_id), INDEX IDX_FA6E3B3E62EF68D (value_resource_match_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;");
        $conn->exec("ALTER TABLE relators ADD CONSTRAINT FK_FA6E3B3E89329D25 FOREIGN KEY (resource_id) REFERENCES resource (id) ON DELETE CASCADE;");
        $conn->exec("ALTER TABLE relators ADD CONSTRAINT FK_FA6E3B3E549213EC FOREIGN KEY (property_id) REFERENCES property (id) ON DELETE CASCADE;");
        $conn->exec("ALTER TABLE relators ADD CONSTRAINT FK_FA6E3B3E62EF68D FOREIGN KEY (value_resource_match_id) REFERENCES resource (id) ON DELETE CASCADE;");
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $conn = $serviceLocator->get('Omeka\Connection');
        $conn->exec('DROP TABLE IF EXISTS relators;');
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        // Add the "has_markers" filter to item search.
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\ItemAdapter',
            'api.search.query',
            [$this, 'handleApiSearchQuery']
        );
        // Add the Relators term definition.
        $sharedEventManager->attach(
            '*',
            'api.context',
            [$this, 'filterApiContext']
        );
        $sharedEventManager->attach(
            'Omeka\Api\Representation\ItemRepresentation',
            'rep.resource.json',
            [$this, 'filterJsonLd']
        );
        $sharedEventManager->attach(
            'Omeka\Api\Representation\ValueRepresentation',
            'rep.value.html',
            [$this, 'repValueHtml'],
            -1
        );
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\ItemAdapter',
            'api.hydrate.post',
            [$this, 'handleRelators']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.add.form.after',
            [$this, 'handleViewFormAfter']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.edit.form.after',
            [$this, 'handleViewFormAfter']
        );
    }

    public function handleApiSearchQuery(Event $event)
    {
        $query = $event->getParam('request')->getContent();
        if (isset($query['has_relators'])) {
            $qb = $event->getParam('queryBuilder');
            $thisResourceAdapter = $event->getTarget();
            $relatorsAlias = $thisResourceAdapter->createAlias();
            $qb->innerJoin(
                'Relators\Entity\Relators',
                $relatorsAlias,
                'WITH',
                "$relatorsAlias.resource = omeka_root.id"
            );
        }
    }

    public function filterApiContext(Event $event)
    {
        $context = $event->getParam('context');
        $context['o-module-relators'] = 'http://id.loc.gov/vocabulary/relators';
        $event->setParam('context', $context);
    }

    /**
     * Add the relators data to the resource JSON-LD.
     *
     * Event $event
     */
    public function filterJsonLd(Event $event)
    {
        $resource = $event->getTarget();
        $jsonLd = $event->getParam('jsonLd');
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $response = $api->search('relators', ['resource_id' => $resource->id()]);
        if ($response->getContent() && array_key_exists("dcterms:contributor", $jsonLd)) {
            $relatorsJson = file_get_contents(__DIR__ . "/asset/js/relators.json");
            $relatorsArray = json_decode($relatorsJson, true);
            $simpleRelators = array_column($relatorsArray, "http://www.loc.gov/mads/rdf/v1#authoritativeLabel", "@id");
            $contributorsJsonLd = $jsonLd["dcterms:contributor"];
            $contributorsJsonLd = json_decode(json_encode($contributorsJsonLd), true);
            foreach ($response->getContent() as $relators) {
                foreach ($contributorsJsonLd as $key => $contributorJsonLd) {
                    $contributorJsonLdValue = array_key_exists("o:label", $contributorJsonLd) ? $contributorJsonLd["o:label"] : null;
                    //need to check for type here because resource types also have @id and doesn't relate to what's stored in uriMatch
                    $contributorJsonLdUri = ((array_key_exists("@id", $contributorJsonLd)) && ($contributorJsonLd["type"] == "uri")) ? $contributorJsonLd["@id"] : null;
                    if (!$contributorJsonLdValue) {
                        $contributorJsonLdValue = array_key_exists("@value", $contributorJsonLd) ? $contributorJsonLd["@value"] : null;
                    }
                    $contributorJsonLdValueResourceId = array_key_exists("value_resource_id", $contributorJsonLd) ? $contributorJsonLd["value_resource_id"] : null;
                    $relatorsValueResourceId = $relators->valueResourceMatch() ? $relators->valueResourceMatch()->id() : null;
                    if (($contributorJsonLdValue == $relators->valueMatch()) && ($contributorJsonLdUri == $relators->uriMatch()) && ($contributorJsonLdValueResourceId == $relatorsValueResourceId)) {
                        if ($relators->values()) {
                            $contributorsJsonLd[$key]['o-module-relators:relators'] = [];
                            foreach ($relators->values() as $uri) {
                                $relator_array = array('type' => 'uri', '@id' => $uri, 'o:label' => $simpleRelators[$uri][0]["@value"]);
                                array_push($contributorsJsonLd[$key]['o-module-relators:relators'], $relator_array);
                            }
                        }
                    }
                }
            }
            $jsonLd["dcterms:contributor"] = $contributorsJsonLd;
            $event->setParam('jsonLd', $jsonLd);
        }
    }

    /**
     * Add the relators data to the resource's display values.
     *
     * Event $event
     */
    public function repValueHtml($event)
    {
        $value = $event->getTarget();
        // Only check values that are dcterms:contributor
        if ($value->property()->term() == "dcterms:contributor") {
            $resourceId = $value->resource()->id();
            $api = $this->getServiceLocator()->get('Omeka\ApiManager');
            $response = $api->search('relators', ['resource_id' => $resourceId]);
            if ($response->getContent()) {
                foreach ($response->getContent() as $relators) {
                    if ($relators->property()->id() == $value->property()->id()) {
                        if (($value->value() == $relators->valueMatch()) && ($value->uri() == $relators->uriMatch()) && ($value->valueResource() == $relators->valueResourceMatch())) {
                            $params = $event->getParams();
                            $html = $params['html'];
                            if ($relators->values()) {
                                $relatorsJson = file_get_contents(__DIR__ . "/asset/js/relators.json");
                                $relatorsArray = json_decode($relatorsJson, true);
                                $simpleRelators = array_column($relatorsArray, "http://www.loc.gov/mads/rdf/v1#authoritativeLabel", "@id");
                                $relatorList = [];
                                $relatorString = '';
                                foreach ($relators->values() as $key => $uri) {
                                    array_push($relatorList, $simpleRelators[$uri][0]["@value"]);
                                }
                                if ($relatorList) {
                                    $relatorString = '&nbsp;(' . implode(", ", $relatorList) . ')';
                                }
                                // case with no link tags
                                if (strpos($html, "<a") === false) {
                                    $html = $html . $relatorString;
                                }
                                // case where value is inside link tag
                                elseif (strpos($html, "<a") == 0) {
                                    // resource links need to be inside of span because of icon
                                    if ($value->type() == "resource") {
                                        $pos = strpos($html, "</span>");
                                        $html = substr_replace($html, $relatorString, $pos, 0);
                                    } else {
                                        $pos = strpos($html, "</a>");
                                        $html = substr_replace($html, $relatorString, $pos, 0);
                                    }
                                }
                                // case where value proceeds link tags
                                elseif ($pos = strpos($html, "<a")) {
                                    $html = substr_replace($html, $relatorString, $pos, 0);
                                }
                            }
                            $event->setParam('html', "$html");
                        }
                    }
                }
            }
        }
    }

    /**
     * Handle hydration for relators data.
     *
     * @param Event $event
     */
    public function handleRelators(Event $event)
    {
        $thisResourceAdapter = $event->getTarget();
        $request = $event->getParam('request');
        $resource = $event->getParam('entity');
        $relatorsAdapter = $thisResourceAdapter->getAdapter('relators');

        if ($contributors = $request->getValue('dcterms:contributor')) {
            foreach ($contributors as $key => $contributor) {
                // $values is list of relator uri's
                $values = [];
                // check for values created from form
                if (array_key_exists("o-module-relators:values", $contributor)) {
                    $this->deleteRelators($event);
                    $values = $contributor["o-module-relators:values"];
                }
                // check for values from api json
                elseif (array_key_exists("o-module-relators:relators", $contributor)) {
                    $this->deleteRelators($event);
                    foreach ($contributor["o-module-relators:relators"] as $relator) {
                        $uri = '';
                        if (array_key_exists("@id", $relator)) {
                            $uri = $relator["@id"];
                            array_push($values, $uri);
                        } elseif ((array_key_exists("o:label", $relator)) && !$uri) {
                            $relatorsJson = file_get_contents(__DIR__ . "/asset/js/relators.json");
                            $relatorsArray = json_decode($relatorsJson, true);
                            $simpleRelators = array_column($relatorsArray, "http://www.loc.gov/mads/rdf/v1#authoritativeLabel", "@id");
                            $simplerRelators = array_filter(array_combine(array_keys($simpleRelators), array_column($simpleRelators, 0)));
                            $simplestRelators = array_filter(array_combine(array_keys($simplerRelators), array_column($simplerRelators, "@value")));
                            $uri = array_search($relator["o:label"], $simplestRelators);
                            array_push($values, $uri);
                        } elseif ((array_key_exists("@value", $relator)) && !$uri) {
                            $relatorsJson = file_get_contents(__DIR__ . "/asset/js/relators.json");
                            $relatorsArray = json_decode($relatorsJson, true);
                            $simpleRelators = array_column($relatorsArray, "http://www.loc.gov/mads/rdf/v1#authoritativeLabel", "@id");
                            $simplerRelators = array_filter(array_combine(array_keys($simpleRelators), array_column($simpleRelators, 0)));
                            $simplestRelators = array_filter(array_combine(array_keys($simplerRelators), array_column($simplerRelators, "@value")));
                            $uri = array_search($relator["@value"], $simplestRelators);
                            array_push($values, $uri);
                        }
                    }
                }
                if ($values) {
                    $representation = $thisResourceAdapter->getRepresentation($resource);
                    $contributorValues = $representation->value('dcterms:contributor', ['all' => true]);
                    foreach ($contributorValues as $key => $contributorValue) {
                        $requestContributorValue = array_key_exists("o:label", $contributor) ? $contributor["o:label"] : null;
                        $requestContributorUri = array_key_exists("@id", $contributor) ? $contributor["@id"] : null;
                        if (!$requestContributorValue) {
                            $requestContributorValue = array_key_exists("@value", $contributor) ? $contributor["@value"] : null;
                        }
                        $requestContributorValueResourceId = array_key_exists("value_resource_id", $contributor) ? $contributor["value_resource_id"] : null;
                        $contributorValueResourceId = $contributorValue->valueResource() ? $contributorValue->valueResource()->id() : null;
                        if (($contributorValue->value() == $requestContributorValue) && ($contributorValue->uri() == $requestContributorUri) && ($contributorValueResourceId == $requestContributorValueResourceId)) {
                            //put values in array so it works with adapter
                            $data['o-module-relators:values'] = $values;
                            // Create relators
                            $subRequest = new \Omeka\Api\Request('create', 'relators');
                            $subRequest->setContent($data);
                            $relators = new \Relators\Entity\Relators;
                            $relators->setResource($resource);
                            $propertiesAdapter = $thisResourceAdapter->getAdapter('properties');
                            $relators->setProperty($propertiesAdapter->findEntity($contributorValue->property()->id()));
                            $relators->setValueMatch($contributorValue->value());
                            $relators->setUriMatch($contributorValue->uri());
                            $resourcesAdapter = $thisResourceAdapter->getAdapter('resources');
                            $contributorValue->valueResource() ? $relators->setValueResourceMatch($resourcesAdapter->findEntity($contributorValue->valueResource()->id())) : $relators->setValueResourceMatch(null);
                            $relatorsAdapter->hydrateEntity($subRequest, $relators, new \Omeka\Stdlib\ErrorStore);
                            $relatorsAdapter->getEntityManager()->persist($relators);
                        }
                    }
                }
            }
        } else {
            $representation = $thisResourceAdapter->getRepresentation($resource);
            $contributorValues = $representation->value('dcterms:contributor', ['all' => true]);
            if (!$contributorValues) {
                $this->deleteRelators($event);
            }
        }
    }

    /**
     * Deletes relators data for resources that already exist, that is updates.
     *
     * @param Event $event
     */
    public function deleteRelators(Event $event)
    {
        $resource = $event->getParam('entity');
        $thisResourceAdapter = $event->getTarget();
        if ($resource->getId()) {
            $api = $this->getServiceLocator()->get('Omeka\ApiManager');
            $response = $api->search('relators', ['resource_id' => $resource->getId()]);
            if ($response->getContent()) {
                $relatorsAdapter = $thisResourceAdapter->getAdapter('relators');
                foreach ($response->getContent() as $relators) {
                    // Delete relator
                    $subRequest = new \Omeka\Api\Request('delete', 'relators');
                    $subRequest->setId($relators->id());
                    $relatorsAdapter->deleteEntity($subRequest);
                }
            }
        }
    }

    public function handleViewFormAfter(Event $event)
    {
        $view = $event->getTarget();
        $assetUrl = $view->plugin('assetUrl');
        $view->headLink()->appendStylesheet($assetUrl('css/relators.css', 'Relators'));
        $view->headScript()
          ->appendFile($assetUrl('js/relators-form.js', 'Relators'))
          ->appendScript(sprintf(
              'var localRelatorsJsonURL = "%s";',
              $assetUrl('js/relators.json', 'Relators', false, false)
          ));
        ;
        $vars = $view->vars();
        if ($vars->resource) {
            $relators = $view->api()->search('relators', ['resource_id' => $vars->resource->id()])->getContent();
            if ($relators) {
                $view->headScript()->appendScript(sprintf(
                    'var valuesRelators = %s;',
                    json_encode($relators)
                ));
            }
        }
    }
}
