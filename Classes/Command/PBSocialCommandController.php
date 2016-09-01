<?php
namespace PlusB\PbSocial\Command;

use PlusB\PbSocial\Controller\ItemController;

class PBSocialCommandController extends \TYPO3\CMS\Extbase\Mvc\Controller\CommandController
{

    /**
     * @var \PlusB\PbSocial\Controller\ItemController
     * @inject
     */
    protected $itemController;

    /**
     * @var \PlusB\PbSocial\Domain\Repository\ItemRepository
     * @inject
     */
    protected $itemRepository;

     /**
     * @var \PlusB\PbSocial\Domain\Repository\CredentialRepository
     * @inject
     */
    protected $credentialRepository;

    /**
     * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
     * @inject
     */
    protected $configurationManager;

    /**
     * @var \TYPO3\CMS\Core\Database\DatabaseConnection
     */
    protected $db;

    private function getDB()
    {
        return $GLOBALS['TYPO3_DB'];
    }

    /**
     * @var \TYPO3\CMS\Extbase\Service\FlexFormService
     * @inject
     */
    protected $flexformService;

    /**
     *  Updates database with feeds
     */
    public function updateFeedDataCommand()
    {

        # Get extension configuration #
        $extConf = @unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['pb_social']);

        # Setup database connection and fetch all flexform settings #
        $this->db = $this->getDB();
        $xml_settings = $this->db->exec_SELECTgetRows('pi_flexform', 'tt_content', 'CType = "list" AND list_type = "pbsocial_socialfeed"');

        # Convert flexform settings into usable array structure #
        if (!empty($xml_settings)) {

            # Update feeds #
            foreach ($xml_settings as $xml_string) {
                $settings = $this->flexform2SettingsArray($xml_string);
                $this->itemController->getFeeds($extConf, $settings, $this->itemRepository, $this->credentialRepository);
            }
        }
    }

    /** Converts some complex flexform xml structure into an easy to use array.
     *
     * @param $xml_string
     * @return array
     */
    public function flexform2SettingsArray($xml_string)
    {
        $xml_obj = simplexml_load_string($xml_string['pi_flexform']);
        $settings = array();
        $extract = 'settings.';

        # Traverse all sheet nodes #
        /** @var \SimpleXMLElement $sheet */
        foreach ($xml_obj->children()->children() as $sheet) {

            # Get data from field nodes #
            /** @var \SimpleXMLElement $field */
            foreach ($sheet->children()->children() as $field) {

                # if index is settings.xyzabcdef* #
                if (strpos($field['index'], $extract) == 0) {
                    $index = str_replace($extract, '', $field['index']);
                    $settings[$index] = (string) $field->children();
                }
            }
        }

        return $settings;
    }
}
