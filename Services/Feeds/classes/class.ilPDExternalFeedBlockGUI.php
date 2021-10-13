<?php

/* Copyright (c) 1998-2021 ILIAS open source, GPLv3, see LICENSE */

/**
 * BlockGUI class for external feed block on the personal desktop.
 * Within the repository ilExternalFeedBlockGUI is used.
 * is used.
 *
 * @author Alex Killing <alex.killing@gmx.de>
 *
 * @ilCtrl_IsCalledBy ilPDExternalFeedBlockGUI: ilColumnGUI
 */
class ilPDExternalFeedBlockGUI extends ilExternalFeedBlockGUI
{
    const FORM_EDIT = 0;
    const FORM_CREATE = 1;
    const FORM_RE_EDIT = 2;
    const FORM_RE_CREATE = 2;

    /**
     * @var ilSetting
     */
    protected $settings;

    public static $block_type = "pdfeed";
    
    /**
    * Constructor
    */
    public function __construct()
    {
        global $DIC;

        $this->ctrl = $DIC->ctrl();
        $this->lng = $DIC->language();
        $this->user = $DIC->user();
        $this->access = $DIC->access();
        $this->settings = $DIC->settings();
        $lng = $DIC->language();
        
        parent::__construct();
        
        $lng->loadLanguageModule("feed");
        
        $this->setLimit(5);
        $this->setRowTemplate("tpl.block_external_feed_row.html", "Services/Feeds");
    }

    /**
     * @inheritdoc
     */
    public function getBlockType() : string
    {
        return self::$block_type;
    }

    /**
     * @inheritdoc
     */
    protected function isRepositoryObject() : bool
    {
        return false;
    }
    
    /**
    * Get Screen Mode for current command.
    */
    public static function getScreenMode()
    {
        global $DIC;

        $ilCtrl = $DIC->ctrl();
        
        switch ($ilCtrl->getCmd()) {
            case "create":
            case "edit":
            case "saveFeedBlock":
            case "updateFeedBlock":
            case "editFeedBlock":
            case "showFeedItem":
            case "confirmDeleteFeedBlock":
                return IL_SCREEN_CENTER;
                break;
                
            default:
                return IL_SCREEN_SIDE;
                break;
        }
    }

    /**
    * Do most of the initialisation.
    */
    public function setBlock($a_block)
    {
        $ilCtrl = $this->ctrl;

        // init block
        $this->feed_block = $a_block;
        $this->setTitle($this->feed_block->getTitle());
        $this->setBlockId($this->feed_block->getId());
        
        // get feed object
        $this->feed = new ilExternalFeed();
        $this->feed->setUrl($this->feed_block->getFeedUrl());
        
        // init details

        $ilCtrl->setParameter($this, "block_id", $this->feed_block->getId());
    }

    /**
    * execute command
    */
    public function executeCommand()
    {
        $ilCtrl = $this->ctrl;

        $next_class = $ilCtrl->getNextClass();
        $cmd = $ilCtrl->getCmd("getHTML");

        switch ($next_class) {
            default:
                return $this->$cmd();
        }
    }

    /**
    * Fill data section
    */
    public function fillDataSection()
    {
        if ($this->getDynamic()) {
            $this->setDataSection($this->getDynamicReload());
        } elseif (count($this->getData()) > 0) {
            parent::fillDataSection();
        } else {
            $this->setDataSection($this->getOverview());
        }
    }

    /**
    * Get block HTML code.
    */
    public function getHTML()
    {
        $ilCtrl = $this->ctrl;
        $lng = $this->lng;
        $ilUser = $this->user;
        $ilSetting = $this->settings;
        

        if ($ilSetting->get("block_limit_pdfeed") == 0) {
            return "";
        }


        // if no dynamic reload
        if (!$this->getDynamic()) {
            $this->feed->fetch();
            $this->setData($this->feed->getItems());
        }

        $ilCtrl->setParameter(
            $this,
            "external_feed_block_id",
            $this->getBlockId()
        );
        $this->addBlockCommand(
            $ilCtrl->getLinkTarget(
                $this,
                "editFeedBlock"
            ),
            $lng->txt("edit")
        );
        $this->addBlockCommand(
            $ilCtrl->getLinkTarget(
                $this,
                "confirmDeleteFeedBlock"
            ),
            $lng->txt("delete")
        );
        $ilCtrl->setParameter($this, "external_feed_block_id", "");

        // JS enabler
        $add = "";
        if ($_SESSION["il_feed_js"] == "n" ||
            ($ilUser->getPref("il_feed_js") == "n" && $_SESSION["il_feed_js"] != "y")) {
            $add = $this->getJSEnabler();
        }
        
        return parent::getHTML() . $add;
    }
    
    public function getDynamic()
    {
        $ilCtrl = $this->ctrl;
        $ilUser = $this->user;
        
        if ($ilCtrl->getCmdClass() != "ilcolumngui" && $ilCtrl->getCmd() != "enableJS") {
            $sess_feed_js = "";
            if (isset($_SESSION["il_feed_js"])) {
                $sess_feed_js = $_SESSION["il_feed_js"];
            }
            if ($sess_feed_js != "n" &&
                ($ilUser->getPref("il_feed_js") != "n" || $sess_feed_js == "y")) {
                // do not get feed dynamically, if cache hit is given.
                if (!$this->feed->checkCacheHit()) {
                    return true;
                }
            }
        }
        
        return false;
    }

    public function getDynamicReload()
    {
        $ilCtrl = $this->ctrl;
        $lng = $this->lng;
        
        $ilCtrl->setParameterByClass(
            "ilcolumngui",
            "block_id",
            "block_pdfeed_" . $this->getBlockId()
        );

        $rel_tpl = new ilTemplate("tpl.dynamic_reload.html", true, true, "Services/Feeds");
        $rel_tpl->setVariable("TXT_LOADING", $lng->txt("feed_loading_feed"));
        $rel_tpl->setVariable("BLOCK_ID", "block_pdfeed_" . $this->getBlockId());
        $rel_tpl->setVariable(
            "TARGET",
            $ilCtrl->getLinkTargetByClass("ilcolumngui", "updateBlock", "", true)
        );
            
        // no JS
        $rel_tpl->setVariable("TXT_FEED_CLICK_HERE", $lng->txt("feed_no_js_click_here"));
        $rel_tpl->setVariable(
            "TARGET_NO_JS",
            $ilCtrl->getLinkTargetByClass("ilpdexternalfeedblockgui", "disableJS")
        );

        return $rel_tpl->get();
    }
    
    public function getJSEnabler()
    {
        $ilCtrl = $this->ctrl;

        $ilCtrl->setParameterByClass(
            "ilcolumngui",
            "block_id",
            "block_pdfeed_" . $this->getBlockId()
        );

        $rel_tpl = new ilTemplate("tpl.js_enabler.html", true, true, "Services/Feeds");
        $rel_tpl->setVariable("BLOCK_ID", "block_pdfeed_" . $this->getBlockId());
        $rel_tpl->setVariable(
            "TARGET",
            $ilCtrl->getLinkTargetByClass("ilpdexternalfeedblockgui", "enableJS", true)
        );
            
        return $rel_tpl->get();
    }
    
    
    public function disableJS()
    {
        $ilCtrl = $this->ctrl;
        $ilUser = $this->user;
        
        $_SESSION["il_feed_js"] = "n";
        $ilUser->writePref("il_feed_js", "n");
        $ilCtrl->redirectByClass("ildashboardgui", "show");
    }
    
    public function enableJS()
    {
        $ilUser = $this->user;
        
        $_SESSION["il_feed_js"] = "y";
        $ilUser->writePref("il_feed_js", "y");
        echo $this->getHTML();
        exit;
    }
    
    /**
    * Fill feed item row
    */
    public function fillRow($item)
    {
        $ilCtrl = $this->ctrl;

        $ilCtrl->setParameter($this, "feed_item_id", $item->getId());
        $this->tpl->setVariable("VAL_TITLE", $item->getTitle());
        $this->tpl->setVariable(
            "HREF_SHOW",
            $ilCtrl->getLinkTarget($this, "showFeedItem")
        );
        $ilCtrl->setParameter($this, "feed_item_id", "");
    }

    /**
    * Get overview.
    */
    public function getOverview()
    {
        $lng = $this->lng;

        $this->setEnableNumInfo(false);
        return '<div class="small">' . ((int) count($this->getData())) . " " . $lng->txt("feed_feed_items") . "</div>";
    }

    /**
    * Show Feed Item
    */
    public function showFeedItem()
    {
        $lng = $this->lng;

        $this->feed->fetch();
        foreach ($this->feed->getItems() as $item) {
            if ($item->getId() == $_GET["feed_item_id"]) {
                $c_item = $item;
                break;
            }
        }
        
        $tpl = new ilTemplate("tpl.show_feed_item.html", true, true, "Services/Feeds");
        
        if (is_object($c_item)) {
            if (trim($c_item->getSummary()) != "") {		// summary
                $tpl->setCurrentBlock("content");
                $tpl->setVariable("VAL_CONTENT", $c_item->getSummary());
                $tpl->parseCurrentBlock();
            }
            if (trim($c_item->getDate()) != "" || trim($c_item->getAuthor()) != "") {		// date
                $tpl->setCurrentBlock("date_author");
                if (trim($c_item->getAuthor()) != "") {
                    $tpl->setVariable("VAL_AUTHOR", $c_item->getAuthor() . " - ");
                }
                $tpl->setVariable("VAL_DATE", $c_item->getDate());
                $tpl->parseCurrentBlock();
            }

            if (trim($c_item->getLink()) != "") {		// link
                $tpl->setCurrentBlock("plink");
                $tpl->setVariable("HREF_LINK", $c_item->getLink());
                $tpl->setVariable("TXT_LINK", $lng->txt("feed_open_source_page"));
                $tpl->parseCurrentBlock();
            }
            $tpl->setVariable("VAL_TITLE", $c_item->getTitle());			// title
        }
        
        $content_block = new ilDashboardContentBlockGUI();
        $content_block->setContent($tpl->get());
        $content_block->setTitle($this->getTitle());

        return $content_block->getHTML();
    }
    
    /**
    * Create Form for Block.
    */
    public function create()
    {
        return $this->createFeedBlock();
    }

    /**
    * FORM FeedBlock: Init form. (We need to overwrite, because Generator
    * does not know FeedUrl Inputs yet.
    *
    * @param	int	$a_mode	Form Edit Mode
    */
    public function initFormFeedBlock($a_mode)
    {
        $lng = $this->lng;
        
        $lng->loadLanguageModule("block");
        
        $this->form_gui = new ilPropertyFormGUI();
        
        // Property Title
        $text_input = new ilTextInputGUI($lng->txt("block_feed_block_title"), "block_title");
        $text_input->setInfo("");
        $text_input->setRequired(true);
        $text_input->setMaxLength(200);
        $this->form_gui->addItem($text_input);
        
        // Property FeedUrl
        $text_input = new ilFeedUrlInputGUI($lng->txt("block_feed_block_feed_url"), "block_feed_url");
        $text_input->setInfo($lng->txt("block_feed_block_feed_url_info"));
        $text_input->setRequired(true);
        $text_input->setMaxLength(250);
        $this->form_gui->addItem($text_input);
        
        
        // save and cancel commands
        if (in_array($a_mode, array(self::FORM_CREATE, self::FORM_RE_CREATE))) {
            $this->form_gui->addCommandButton("saveFeedBlock", $lng->txt("save"));
            $this->form_gui->addCommandButton("cancelSaveFeedBlock", $lng->txt("cancel"));
        } else {
            $this->form_gui->addCommandButton("updateFeedBlock", $lng->txt("save"));
            $this->form_gui->addCommandButton("cancelUpdateFeedBlock", $lng->txt("cancel"));
        }
        
        $this->form_gui->setTitle($lng->txt("block_feed_block_head"));
        $this->form_gui->setFormAction($this->ctrl->getFormAction($this));
        
        $this->prepareFormFeedBlock($this->form_gui);
    }

    /**
    * FORM FeedBlock: Prepare Saving of FeedBlock.
    *
    * @param	object	$a_feed_block	FeedBlock object.
    */
    public function prepareSaveFeedBlock(&$a_feed_block)
    {
        $ilCtrl = $this->ctrl;
        
        $a_feed_block->setContextObjId($ilCtrl->getContextObjId());
        $a_feed_block->setContextObjType($ilCtrl->getContextObjType());
        $a_feed_block->setType("pdfeed");
    }

    /**
    * Confirmation of feed block deletion
    */
    public function confirmDeleteFeedBlock()
    {
        $ilCtrl = $this->ctrl;
        $lng = $this->lng;
        
        $c_gui = new ilConfirmationGUI();
        
        // set confirm/cancel commands
        $c_gui->setFormAction($ilCtrl->getFormAction($this, "deleteFeedBlock"));
        $c_gui->setHeaderText($lng->txt("info_delete_sure"));
        $c_gui->setCancel($lng->txt("cancel"), "exitDeleteFeedBlock");
        $c_gui->setConfirm($lng->txt("confirm"), "deleteFeedBlock");

        // add items to delete
        $c_gui->addItem(
            "external_feed_block_id",
            $this->feed_block->getId(),
            $this->feed_block->getTitle(),
            ilUtil::getImagePath("icon_feed.svg")
        );
        
        return $c_gui->getHTML();
    }
    
    /**
    * Cancel deletion of feed block
    */
    public function exitDeleteFeedBlock()
    {
        $ilCtrl = $this->ctrl;

        $ilCtrl->returnToParent($this);
    }

    /**
    * Delete feed block
    */
    public function deleteFeedBlock()
    {
        $ilCtrl = $this->ctrl;

        $this->feed_block->delete();
        $ilCtrl->returnToParent($this);
    }
}
