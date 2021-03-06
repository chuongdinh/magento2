<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Integration\Model\Plugin;

use Magento\Authorization\Model\Acl\AclRetriever;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Integration\Model\Integration as IntegrationModel;
use Magento\Integration\Api\AuthorizationServiceInterface;
use Magento\Integration\Api\IntegrationServiceInterface;

/**
 * Plugin for \Magento\Integration\Model\IntegrationService.
 */
class Integration
{
    /** @var AuthorizationServiceInterface */
    protected $integrationAuthorizationService;

    /** @var  AclRetriever */
    protected $aclRetriever;

    /**
     * Initialize dependencies.
     *
     * @param AuthorizationServiceInterface $integrationAuthorizationService
     * @param AclRetriever $aclRetriever
     */
    public function __construct(
        AuthorizationServiceInterface $integrationAuthorizationService,
        AclRetriever $aclRetriever
    ) {
        $this->integrationAuthorizationService = $integrationAuthorizationService;
        $this->aclRetriever  = $aclRetriever;
    }

    /**
     * Persist API permissions.
     *
     * @param IntegrationServiceInterface $subject
     * @param IntegrationModel $integration
     *
     * @return IntegrationModel
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterCreate(IntegrationServiceInterface $subject, $integration)
    {
        $this->_saveApiPermissions($integration);
        return $integration;
    }

    /**
     * Persist API permissions.
     *
     * @param IntegrationServiceInterface $subject
     * @param IntegrationModel $integration
     *
     * @return IntegrationModel
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterUpdate(IntegrationServiceInterface $subject, $integration)
    {
        $this->_saveApiPermissions($integration);
        return $integration;
    }

    /**
     * Add API permissions to integration data.
     *
     * @param IntegrationServiceInterface $subject
     * @param IntegrationModel $integration
     *
     * @return IntegrationModel
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGet(IntegrationServiceInterface $subject, $integration)
    {
        $this->_addAllowedResources($integration);
        return $integration;
    }

    /**
     * Add the list of allowed resources to the integration object data by 'resource' key.
     *
     * @param IntegrationModel $integration
     * @return void
     */
    protected function _addAllowedResources(IntegrationModel $integration)
    {
        if ($integration->getId()) {
            $integration->setData(
                'resource',
                $this->aclRetriever->getAllowedResourcesByUser(
                    UserContextInterface::USER_TYPE_INTEGRATION,
                    (int)$integration->getId()
                )
            );
        }
    }

    /**
     * Persist API permissions.
     *
     * Permissions are expected to be set to integration object by 'resource' key.
     * If 'all_resources' is set and is evaluated to true, permissions to all resources will be granted.
     *
     * @param IntegrationModel $integration
     * @return void
     */
    protected function _saveApiPermissions(IntegrationModel $integration)
    {
        if ($integration->getId()) {
            if ($integration->getData('all_resources')) {
                $this->integrationAuthorizationService->grantAllPermissions($integration->getId());
            } elseif (is_array($integration->getData('resource'))) {
                $this->integrationAuthorizationService
                    ->grantPermissions($integration->getId(), $integration->getData('resource'));
            } else {
                $this->integrationAuthorizationService->grantPermissions($integration->getId(), []);
            }
        }
    }

    /**
     * Process integration resource permissions after the integration is created
     *
     * @param IntegrationServiceInterface $subject
     * @param array $integrationData Data of integration deleted
     *
     * @return array $integrationData
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterDelete(IntegrationServiceInterface $subject, array $integrationData)
    {
        //No check needed for integration data since it cannot be empty in the parent invocation - delete
        $integrationId = (int)$integrationData[IntegrationModel::ID];
        $this->integrationAuthorizationService->removePermissions($integrationId);
        return $integrationData;
    }
}
