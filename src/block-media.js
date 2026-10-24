import original from '../resources/block-media.json';
import { tablerIcon } from './editor-icons.js';
// Original media from pinned GrapesJS plugins (BSD-3-Clause; see THIRD_PARTY_NOTICES.md). This catalogue is UI-only.
const aliases={'text-link':'link',heading:'text',paragraph:'textarea','link-block':'link',container:'column1'};
const sections={hero:'device-desktop',features:'layout-grid',cta:'arrow-right',navigation:'menu',process:'layout-distribute-horizontal',testimonial:'typography',faq:'text-wrap',footer:'align-box-bottom-center',flexbox:'layout-distribute-horizontal','bullet-list':'menu',divider:'spacing-vertical',spacer:'arrows-vertical',quote:'typography','content-card':'file-text','image-card':'photo','card-grid':'layout-grid',article:'file-text','article-grid':'columns-3',gallery:'photo',pricing:'columns-3',team:'layout-grid','cover-centered':'align-box-center-middle','cover-split':'columns-3','contact-details':'file-text','footer-columns':'align-box-bottom-center'};
export const blockMedia=id=>original[id]||original[aliases[id]]||tablerIcon(sections[id]||'square');
