<?php

namespace App\Helper;

class Embed {
    public function getUrl($project = null) {
        return 'https://voiceep.com/' . ($project ? $project->getIdentifier() : 123456789) . '/embed.js';
    }
}
