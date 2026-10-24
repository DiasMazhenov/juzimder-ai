import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { blocks } from '../src/blocks.js';
import { decodeCustomCode } from '../src/custom-code.js';
import { DEFAULT_DESIGN_TOKENS, designTokensToStyle } from '../src/design-tokens.js';

test('section and element library has unique ids and complete labels', () => {
  const ids = blocks.map(block => block.id);
  assert.equal(new Set(ids).size, ids.length, 'block ids must be unique');
  assert.deepEqual(ids.slice(0,12), ['hero', 'features', 'cta', 'text', 'image', 'navigation', 'process', 'testimonial', 'faq', 'footer', 'container', 'button']);
  assert.ok(ids.length>=32,'Library includes original sections and expanded native presets');
  for (const block of blocks) {
    assert.ok(block.label.trim(), `${block.id} has a visible label`);
    assert.ok(block.description.trim(), `${block.id} has a description`);
    assert.notStrictEqual(block.content(), block.content(), `${block.id} returns fresh content for each insertion`);
  }
});

test('internal links resolve when the complete section library is composed', () => {
  const ids = new Set();
  const targets = [];
  const visit = value => {
    if (Array.isArray(value)) return value.forEach(visit);
    if (!value || typeof value !== 'object') return;
    if (typeof value.attributes?.id === 'string') ids.add(value.attributes.id);
    if (typeof value.attributes?.href === 'string' && value.attributes.href.startsWith('#')) targets.push(value.attributes.href.slice(1));
    Object.values(value).forEach(visit);
  };
  blocks.forEach(block => visit(block.content()));
  for (const target of targets) assert.ok(ids.has(target), `#${target} should point to a section in the library`);
});

test('all block presets pass the authoritative PHP Project Data validator', () => {
  const document = {
    schemaVersion: 1,
    engine: 'grapesjs',
    data: {
      assets: [],
      styles: [],
      pages: [{
        id: 'home',
        name: 'Главная',
        frames: [{ component: { type: 'wrapper', style: designTokensToStyle(DEFAULT_DESIGN_TOKENS), components: blocks.map(block => block.content()) } }]
      }]
    }
  };
  const validation = spawnSync('php', ['-r', "require_once getenv('JUZIMDER_DOMAIN_PATH'); \\Juzimder\\Document::validate(json_decode(stream_get_contents(STDIN)));"], {
    encoding: 'utf8',
    input: JSON.stringify(document),
    env: { ...process.env, JUZIMDER_DOMAIN_PATH: fileURLToPath(new URL('../backend/Domain.php', import.meta.url)) }
  });
  assert.equal(validation.status, 0, validation.stderr || validation.stdout || 'PHP validator rejected the section library');
});

test('new section presets use editable global tokens for shared colors, typography and radius', () => {
  const cssValues = [];
  const visit = value => {
    if (Array.isArray(value)) return value.forEach(visit);
    if (!value || typeof value !== 'object') return;
    for (const entry of Object.values(value.style || {})) cssValues.push(entry);
    Object.values(value).forEach(visit);
  };
  blocks.forEach(block => visit(block.content()));
  assert.ok(cssValues.some(value => String(value).includes('--juzimder-color-ink')));
  assert.ok(cssValues.some(value => String(value).includes('--juzimder-font-heading')));
  assert.ok(cssValues.some(value => String(value).includes('--juzimder-font-body')));
  assert.ok(cssValues.some(value => String(value).includes('--juzimder-font-size-h1')));
  assert.ok(cssValues.some(value => String(value).includes('--juzimder-radius')));
});

test('new library families are present and image placeholders are explicitly trusted',()=>{
  const categories=new Set(blocks.map(block=>block.category));
  for(const category of ['Карточки','Статьи','Галереи','Цены','Команда','Обложки'])assert.ok(categories.has(category),category);
  const gallery=blocks.find(block=>block.id==='gallery').content();
  const images=gallery.components[1].components;
  assert.equal(images.length,4);
  for(const image of images){assert.ok(image.attributes.src.startsWith('data:image/png;base64,'));assert.equal(image.type,'image');}
});

test('expanded section presets keep badges compact and responsive grids',()=>{
  const ids=['account-recovery-guide','planned-maintenance','compatibility-matrix','data-migration-guide','api-integration-guide','admin-roles-guide','trial-period-guide','service-level-overview','size-fit-guide','product-origin','accommodation-info','reservation-policy','event-access-guide','incident-report','security-disclosure','membership-benefits','return-policy','subscription-management','privacy-request-guide','grant-application','press-kit','conference-session','course-enrollment','service-status','property-listing','rental-terms','trip-itinerary','menu-allergens','clinic-visit-prep','software-requirements','research-methodology','donation-transparency','vacancy-detail','course-outcomes','event-ticket-options','venue-guidelines','volunteer-roles','case-results','project-roadmap','warranty-care','breadcrumbs','article-byline','release-notes','product-variants','feature-matrix','onboarding-checklist','support-expectations','privacy-contacts','branch-locations','event-calendar','review-sources','awards-history','email-updates','accessibility-info','community-participation','newsletter-contact','credentials','partner-network','social-channels','payment-options','service-coverage','metrics-strip','question-groups','image-comparison','option-comparison','before-start','download-catalog','page-directory','service-boundaries','appointment-info','exhibition-grid','policy-summary','cover-panorama','feature-spotlight','destination-grid','event-announcement','case-timeline','offer-inclusions','photo-story','founder-letter','press-feature','opening-hours','course-program','learning-format','instructor-grid','audience-fit','project-deliverables','collaboration-steps','job-openings','resource-library','office-grid','support-channels','product-catalog','product-detail','restaurant-menu','weekly-schedule','document-links','service-detail','delivery-info','location-card','editorial-intro','gallery-captions','about-split','services-list','benefits-split','portfolio-grid','case-study','faq-grid','price-list','event-program','team-profile','cta-split'];
  for(const id of ids){
    const root=blocks.find(block=>block.id===id).content();
    assert.equal(root.tagName,id==='breadcrumbs'?'nav':'section');
    if(id!=='breadcrumbs')assert.equal(root.components[0].style.width,'fit-content',id+' badge must not stretch');
    const visit=node=>{if(!node||typeof node!=='object')return;if(node.style?.display==='grid')assert.match(node.style['grid-template-columns'],/auto-fit.*min\(100%/,id+' grid must collapse');for(const child of node.components||[])visit(child);assert.ok(!node.script,id+' cannot depend on scripts');};
    visit(root);
  }
});

test('Navbar, Countdown, Slider, Tabs, Tooltip and Custom Code are registered as native blocks',()=>{
  const byId=new Map(blocks.map(block=>[block.id,block]));
  for(const id of ['navbar','countdown','slider','tabs','tooltip','custom-code'])assert.ok(byId.has(id),`${id} should be in the editor library`);
  assert.equal(byId.get('navbar').content().tagName,'header');
  assert.equal(byId.get('countdown').content().type,'countdown');
  const slider=byId.get('slider').content();
  assert.equal(slider.components[1].style['scroll-snap-type'],'x mandatory');
  assert.equal(slider.components[1].components.length,3);
  const tabs=byId.get('tabs').content();
  assert.equal(tabs.type,'custom-code');
  const tabsCode=decodeCustomCode(tabs.content);
  assert.equal((tabsCode.html.match(/type="radio"/g)||[]).length,3);
  assert.match(tabsCode.html,/class="jz-tab-panels"/);
  assert.match(tabsCode.css,/\.jz-tab-toggle:nth-of-type\(3\):checked/);
  assert.equal(tabsCode.js,'','Tabs must not add executable JavaScript');
  assert.ok(byId.get('tooltip').content().attributes.title);
  assert.match(byId.get('custom-code').content().content,/^jzcc1\./);
});
