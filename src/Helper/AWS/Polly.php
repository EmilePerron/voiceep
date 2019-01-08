<?php

namespace App\Helper\AWS;

use App\Entity\Voiceover;

class Polly {
    static $client = null;
    const S3_FILE_PREFIX = 'polly';
    const MAX_CHARACTER_COUNT = 100000;

    protected static function getClient() {
        if (static::$client === null) {
            static::$client = AWS::getSdk()->createPolly();
        }

        return static::$client;
    }

    public static function getAvailableVoices() {
        return [
            'cmn-CN' => [
                'male' => [],
                'female' => ['Zhiyu']],
            'da-DK' => [
                'male' => ['Mads'],
                'female' => ['Naja']],
            'nl-NL' => [
                'male' => ['Ruben'],
                'female' => ['Lotte']],
            'en-AU' => [
                'male' => ['Russell'],
                'female' => ['Nicole']],
            'en-GB' => [
                'male' => ['Brian'],
                'female' => ['Amy', 'Emma']],
            'en-IN' => [
                'male' => [],
                'female' => ['Aditi', 'Raveena']],
            'en-US' => [
                'male' => ['Joey', 'Justin', 'Matthew'],
                'female' => ['Ivy', 'Joanna', 'Kendra', 'Kimberly', 'Salli']],
            'en-GB-WLS' => [
                'male' => ['Geraint'],
                'female' => []],
            'fr-FR' => [
                'male' => ['Mathieu'],
                'female' => ['Celine', 'Léa']],
            'fr-CA' => [
                'male' => [],
                'female' => ['Chantal']],
            'de-DE' => [
                'male' => ['Hans'],
                'female' => ['Marlene', 'Vicki']],
            'hi-IN' => [
                'male' => [],
                'female' => ['Aditi']],
            'is-IS' => [
                'male' => ['Karl'],
                'female' => ['Dora']],
            'it-IT' => [
                'male' => ['Giorgio'],
                'female' => ['Carla']],
            'ja-JP' => [
                'male' => ['Takumi'],
                'female' => ['Mizuki']],
            'ko-KR' => [
                'male' => [],
                'female' => ['Seoyeon']],
            'nb-NO' => [
                'male' => [],
                'female' => ['Liv']],
            'pl-PL' => [
                'male' => ['Jacek', 'Jan'],
                'female' => ['Ewa', 'Maja']],
            'pt-BR' => [
                'male' => ['Ricardo'],
                'female' => ['Vitoria']],
            'pt-PT' => [
                'male' => ['Cristiano'],
                'female' => ['Ines']],
            'ro-RO' => [
                'male' => [],
                'female' => ['Carmen']],
            'ru-RU' => [
                'male' => ['Maxim'],
                'female' => ['Tatyana']],
            'es-ES' => [
                'male' => ['Enrique'],
                'female' => ['Conchita']],
            'es-US' => [
                'male' => ['Miguel'],
                'female' => ['Penelope']],
            'sv-SE' => [
                'male' => [],
                'female' => ['Astrid']],
            'tr-TR' => [
                'male' => [],
                'female' => ['Filiz']],
            'cy-GB' => [
                'male' => [],
                'female' => ['Gwyneth']]
        ];
    }

    public static function getAvailableLanguageCodes($groupByLang = false) {
        $availableLanguageCodes = [];

        $availablePollyVoices = Polly::getAvailableVoices();
        foreach ($availablePollyVoices as $languageCode => $voices) {
            if ($groupByLang) {
                list($langCode, $regionCode) = explode('-', $languageCode);
                $languageName = \Locale::getDisplayLanguage($languageCode);

                if (!isset($availableLanguageCodes[$languageName])) {
                    $availableLanguageCodes[$languageName] = [];
                }

                $availableLanguageCodes[$languageName][$languageCode] = $languageCode;
            } else {
                $availableLanguageCodes[$languageCode] = $languageCode;
            }
        }

        if ($groupByLang) {
            ksort($availableLanguageCodes);
        } else {
            asort($availableLanguageCodes);
        }

        return $availableLanguageCodes;
    }

    protected static function getDefaultVoiceForLanguageCode(String $languageCode, $preferredGender = 'male') {
        $voices = static::getAvailableVoices();

        if (isset($voices[$languageCode])) {
            if (count($voices[$languageCode][$preferredGender] ?? [])) {
                # Return the first voice of the desired language and gender
                return $voices[$languageCode][$preferredGender][0];
            } else {
                foreach ($voices[$languageCode] as $gender) {
                    if (count($voices[$languageCode][$gender])) {
                        # Fallback on the first voice of the desired language
                        return $voices[$languageCode][$gender][0];
                    }
                }
            }
        }

        # Fallback on a voice from the same language, but from a different region
        $language = substr($languageCode, 0, 2);
        foreach ($voices as $voiceLanguageCode => $genders) {
            if (substr($voiceLanguageCode, 0, 2) == $language) {
                if (count($voices[$voiceLanguageCode][$preferredGender] ?? [])) {
                    # Fallback using the preferred gender
                    return $voices[$voiceLanguageCode][$preferredGender][0];
                } else {
                    foreach ($genders as $gender) {
                        if (count($voices[$voiceLanguageCode][$gender])) {
                            # Fallback using the first available gender
                            return $voices[$voiceLanguageCode][$gender][0];
                        }
                    }
                }
            }
        }

        return null;
    }

    protected static function getDataFromVoiceover(Voiceover &$voiceover) {
        $voice = $voiceover->getOption('voice') ?: static::getDefaultVoiceForLanguageCode($voiceover->getLanguageCode(), $voiceover->getOption('gender') ?: 'female');
        $voiceover->setOption('voice', $voice);
        $options = [
            'LanguageCode' => $voiceover->getLanguageCode(),
            #'LexiconNames' => ['<string>', ...],
            'OutputFormat' => 'mp3', # json|mp3|ogg_vorbis|pcm
            'OutputS3BucketName' => S3::DEFAULT_BUCKET,
            'OutputS3KeyPrefix' => static::S3_FILE_PREFIX,
            #'SampleRate' => '<string>',
            #'SnsTopicArn' => '<string>',
            #'SpeechMarkTypes' => ['<string>', ...],
            'Text' => $voiceover->getText(),
            'TextType' => 'text',
            'VoiceId' => $voice
        ];
        return $options;
    }

    public static function startSynthesizing(Voiceover &$voiceover) {
        $synthesisData = static::getDataFromVoiceover($voiceover);

        if (strlen($synthesisData['Text']) > static::MAX_CHARACTER_COUNT) {
            throw new \Exception("The provided text is too long to be synthesized. The maximum length is of " . static::MAX_CHARACTER_COUNT . " characters, and your text has " . strlen($synthesisData['Text'])  . ".");
        }

        $response = static::getClient()->startSpeechSynthesisTask($synthesisData);

        # If a result object is returned, turn it into an array
        if ($response instanceof \Aws\Result) {
            $response = ['SynthesisTask' => $response->get('SynthesisTask'),
                        '@metadata' => $response->get('@metadata')];
        }

        AWS::checkResponseStatus($response, "An error occured while attempting to synthesize the provided text.");

        $voiceover->setStatus($response['SynthesisTask']['TaskStatus'])
        ->setOption('task_id', $response['SynthesisTask']['TaskId'])
        ->setLanguageCode($response['SynthesisTask']['LanguageCode']);

        return $response['SynthesisTask'] ?? null;
    }

    public static function fetchTaskUpdatesFromVoiceover(Voiceover &$voiceover) {
        $taskId = $voiceover->getOption('task_id');

        if (!$taskId) {
            throw new \Exception("This voiceover is not awaiting processing.");
        }

        $response = static::getClient()->getSpeechSynthesisTask(['TaskId' => $taskId]);
        AWS::checkResponseStatus($response, "An error occured while fetching the synthesis task details.");

        if ($response['SynthesisTask']['TaskStatus'] != $voiceover->getStatus()) {
            $voiceover->setStatus($response['SynthesisTask']['TaskStatus']);

            if ($response['SynthesisTask']['TaskStatus'] == 'completed') {
                $voiceover->setOption('file_url', CloudFront::buildUrlFromS3Url($response['SynthesisTask']['OutputUri']));
                $voiceover->setOption('file_format', $response['SynthesisTask']['OutputFormat']);
            } else if ($response['SynthesisTask']['TaskStatus'] == 'failed') {
                $voiceover->setOption('processing_error_message', $response['SynthesisTask']['TaskStatusReason']);
            }
        }
    }
}
