<?php
declare(strict_types=1);

namespace WapplerSystems\Cleverreach\Form\Finishers;


/**
 * This file is part of the "cleverreach" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */


use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Form\Domain\Finishers\AbstractFinisher;
use TYPO3\CMS\Form\Domain\Finishers\Exception\FinisherException;
use TYPO3\CMS\Form\Domain\Model\FormElements\FormElementInterface;
use WapplerSystems\Cleverreach\CleverReach\Api;
use WapplerSystems\Cleverreach\Domain\Model\Receiver;
use WapplerSystems\Cleverreach\Service\ConfigurationService;


class CleverreachFinisher extends AbstractFinisher
{

    /**
     * @var \WapplerSystems\Cleverreach\CleverReach\Api
     * @TYPO3\CMS\Extbase\Annotation\Inject
     */
    protected $api;


    /**
     * @var array
     */
    protected $defaultOptions = [
    ];


    /**
     * Executes this finisher
     * @see AbstractFinisher::execute()
     *
     * @throws FinisherException
     */
    protected function executeInternal()
    {

        $formValues = $this->getFormValues();

        /** @var ConfigurationService $configurationService */
        $configurationService = GeneralUtility::makeInstance(ObjectManager::class)->get(ConfigurationService::class);
        $configuration = $configurationService->getConfiguration();


        $groupId = isset($this->options['groupId']) && \strlen($this->options['groupId']) > 0 ? $this->options['groupId'] : $configuration['groupId'];
        $formId = isset($this->options['formId']) && \strlen($this->options['formId']) > 0 ? $this->options['formId'] : $configuration['formId'];

        if (empty($groupId) || empty($formId)) throw new FinisherException('Form ID or Group ID not set.');

        $email = '';
        $connectToAPI = false;
        $hasActivatorField = false;
        $attributes = [];
        $globalAttributes = [];


        foreach ($formValues as $identifier => $value) {

            $element = $this->finisherContext->getFormRuntime()->getFormDefinition()->getElementByIdentifier($identifier);
            if ($element) {
                $properties = $element->getProperties();
                if (isset($properties['cleverreachField'])) {
                    if ($properties['cleverreachField'] === 'email') {
                        $email = $value;
                    } else {
                        if(API::ATTRIBUTES_LOCAL === strtolower($properties['cleverreachAttributeLocalization'] ?? '')) {
                            $attributes[$properties['cleverreachField']] = $value;
                        } else {
                            $globalAttributes[$properties['cleverreachField']] = $value;
                        }
                    }
                }
                
                if($properties['cleverreachActivatorField'] === true) {
                    $hasActivatorField = true;
                    
                    if($value == '1') {
                        $connectToAPI = true;
                    }
                }
            }
        }
        
        if(!$hasActivatorField) {
            $connectToAPI = true;
        }
        
        if (isset($this->options['mode']) && \strlen($email) > 0 && $connectToAPI === true) {

            if (\strtolower($this->options['mode']) === Api::MODE_OPTIN) {

                $receiver = new Receiver($email, $attributes, $globalAttributes);
                $this->api->addReceiversToGroup($receiver, $groupId);
                $this->api->sendSubscribeMail($email, $formId, $groupId);

            } else if (\strtolower($this->options['mode']) === Api::MODE_OPTOUT) {

                $this->api->sendUnsubscribeMail($email, $formId, $groupId);

            }

        }
    }


    /**
     * Returns the values of the submitted form
     *
     * @return []
     */
    protected function getFormValues(): array
    {
        return $this->finisherContext->getFormValues();
    }

    /**
     * Returns a form element object for a given identifier.
     *
     * @param string $elementIdentifier
     * @return NULL|FormElementInterface
     */
    protected function getElementByIdentifier(string $elementIdentifier)
    {
        return $this
            ->finisherContext
            ->getFormRuntime()
            ->getFormDefinition()
            ->getElementByIdentifier($elementIdentifier);
    }



}
