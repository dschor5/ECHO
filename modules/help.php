<?php

class HelpModule extends DefaultModule
{
    public function __construct(&$user)
    {
        parent::__construct($user);
        $this->subJsonRequests = array();
        $this->subHtmlRequests = array(
            'overview'      => 'showHelpOverview', 
            'main'          => 'showHelpOverview'
        );

        $mission = MissionConfig::getInstance();

        if($mission->feat_convo_threads)
        {
            $this->subHtmlRequests['threads'] = 'showHelpThreads';
        }

        if($mission->feat_markdown_support)
        {
            $this->subHtmlRequests['markdown'] = 'showHelpMarkdown';
        }

        if($this->user->is_admin)
        {
            $this->subHtmlRequests['users'] = 'showHelpAdmUsers';
            $this->subHtmlRequests['delay'] = 'showHelpAdmDelay';
            $this->subHtmlRequests['mission'] = 'showHelpAdmMission';
            $this->subHtmlRequests['data'] = 'showHelpAdmData';
        }
   }

    protected function showHelpOverview() : string
    {
        $this->addTemplates('common.css', 'settings.css');

        return Main::loadTemplate('help.txt', array(
            '/%menu%/' => $this->showHelpMenu(),
            '/%content%/' => Main::loadTemplate('help-overview.txt'),
        ));
    }

    protected function showHelpThreads() : string
    {
        $this->addTemplates('common.css', 'settings.css');

        return Main::loadTemplate('help.txt', array(
            '/%menu%/' => $this->showHelpMenu(),
            '/%content%/' => Main::loadTemplate('help-threads.txt'),
        ));
    }

    protected function showHelpMarkdown() : string
    {
        $this->addTemplates('common.css', 'settings.css');

        return Main::loadTemplate('help.txt', array(
            '/%menu%/' => $this->showHelpMenu(),
            '/%content%/' => Main::loadTemplate('help-markdown.txt'),
        ));
    }

    protected function showHelpAdmUsers() : string
    {
        $this->addTemplates('common.css', 'settings.css');

        return Main::loadTemplate('help.txt', array(
            '/%menu%/' => $this->showHelpMenu(),
            '/%content%/' => Main::loadTemplate('help-adm-users.txt'),
        ));
    }

    protected function showHelpAdmDelay() : string
    {
        $this->addTemplates('common.css', 'settings.css');

        return Main::loadTemplate('help.txt', array(
            '/%menu%/' => $this->showHelpMenu(),
            '/%content%/' => Main::loadTemplate('help-adm-delay.txt'),
        ));
    }

    protected function showHelpAdmMission() : string
    {
        $this->addTemplates('common.css', 'settings.css');

        return Main::loadTemplate('help.txt', array(
            '/%menu%/' => $this->showHelpMenu(),
            '/%content%/' => Main::loadTemplate('help-adm-mission.txt'),
        ));
    }

    protected function showHelpAdmData() : string
    {
        $this->addTemplates('common.css', 'settings.css');

        return Main::loadTemplate('help.txt', array(
            '/%menu%/' => $this->showHelpMenu(),
            '/%content%/' => Main::loadTemplate('help-adm-data.txt'),
        ));
    }

    private function showHelpMenu() : string 
    {
        $mission = MissionConfig::getInstance();

        $links = array(
            'overview' => 'Getting Started'
        );

        if($mission->feat_convo_threads)
        {
            $links['threads'] = 'Conversation threads';
        }

        if($mission->feat_markdown_support)
        {
            $links['markdown'] = 'Markdown Basics';
        }

        if($this->user->is_admin)
        {
            $links['users'] = 'User Accounts';
            $links['delay'] = 'Delay Settings';
            $links['mission'] = 'Mission Settings';
            $links['data'] = 'Data Management';
        }

        $current = $_GET['subaction'] ?? 'overview';

        $linkHtml = '';
        foreach($links as $url => $text)
        {
            if($current == $url)
            {
                $linkHtml .= '<li><b>'.$text.'</b></li>';
            }
            else
            {
                $linkHtml .= '<li><a href="%http%%site_url%/help/'.$url.'">'.$text.'</a></li>';
            }
        }

        return $linkHtml;
    }
}


?>