<?php

declare(strict_types=1);

/**
 * Copyright 2021 whojinn

 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at

 *  http://www.apache.org/licenses/LICENSE-2.0

 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Whojinn\Danraku\Listener;

use League\CommonMark\Event\DocumentRenderedEvent;
use League\CommonMark\Output\RenderedContent;
use League\Config\ConfigurationAwareInterface;
use League\Config\ConfigurationInterface;

/**
 * 基本的に、所謂インライン内で動作するように設定されている。
 */
class DanrakuPostRenderer implements ConfigurationAwareInterface
{
    private const TOP = '^<(p|(p .*?))>';
    private const BASIC = '(?!<img (.*?)';
    private const BOTTOM = ')';

    private const FOOT_NOTE_BEGIN = 'class="footnote"';
    private const FOOT_NOTE_END = '</li>';

    private $config;

    /**
     * 基本形 '^<(p|(p .*?))>(?!<img (.*?))'
     * */
    private function setPattern(): string
    {

        $basic_pattern = self::TOP . self::BASIC;


        // 設定の羅列
        $ignore_alpha = $this->config->get('danraku/ignore_alphabet');

        // 以下、設定
        if ($ignore_alpha) {
            $basic_pattern .= '|([[:alpha:]]+?)';
        }

        return $basic_pattern . self::BOTTOM;
    }

    public function setConfiguration(ConfigurationInterface $configuration): void
    {
        $this->config = $configuration;
    }

    public function postRender(DocumentRenderedEvent $event)
    {
        // 文を改行ごとに分割する
        $html_array = mb_split("\n", $event->getOutput()->getContent());

        $document = $event->getOutput()->getDocument();
        $pattern = $this->setPattern();

        // trueにすると、脚注には全角スペースを入れない
        $ignore_footnote = $this->config->get('danraku/ignore_footnote');
        $footnote_flag = false;

        // バッファ
        $replaced = "";

        // 置換したコードをバッファに追加
        foreach ($html_array as $html) {

            // 基本的な置換。
            if (!$footnote_flag && mb_ereg($pattern, $html, $match)) {
                $replaced .= mb_ereg_replace($pattern, $match[0] . "　", $html);
            } else {
                $replaced .= $html;
            }

            // 脚注があったときにはfootnote_flagを立てる(</p>が来たら倒す)
            if ($ignore_footnote && mb_ereg(self::FOOT_NOTE_BEGIN, $html)) {
                $footnote_flag = true;
            }
            if ($footnote_flag && mb_ereg(self::FOOT_NOTE_END, $html)) {
                $footnote_flag = true;
            }

            // 行末に消した改行コードを加える
            if (!mb_ereg('^$', $html)) {
                $replaced .= "\n";
            }
        }


        // 最後にまとめて置換
        $event->replaceOutput(new RenderedContent($document, $replaced));
    }
}
