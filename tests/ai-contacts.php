<?php
declare(strict_types=1);
require __DIR__.'/../backend/Ai.php';
use Juzimder\{AiContacts,AiProviderFailure,AiGeneratedWebsite,StructuredAiProvider};
function contactCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);echo "PASS $message\n";}
function contactReject(string $html,string $brief):void{try{AiContacts::audit(['html'=>$html],$brief);}catch(AiProviderFailure $failure){contactCheck($failure->category==='contact_requirements','Reject unprovided contact or external URL');return;}throw new RuntimeException('Expected contact rejection');}
contactCheck(AiContacts::audit(['html'=>'<a href="#booking">Book</a><button>Choose a format</button>'],'Workshop')['status']==='passed','Internal navigation and plain buttons need no invented contacts');
contactCheck(AiContacts::audit(['html'=>'<a href="tel:123">Call</a>'],'Test contact number: 123')['status']==='passed','Explicit short contact numbers remain supported');
$brief='Email qa@example.test. Phone +7 (777) 123-45-67. Site https://example.test/book';
contactCheck(AiContacts::audit(['html'=>'<a href="mailto:qa@example.test">Email</a><a href="tel:+77771234567">Call</a><a href="https://example.test/book">Site</a><p>qa@example.test +7 (777) 123-45-67</p>'],$brief)['checkedOccurrences']===5,'Explicit owner contacts and formatted phones are accepted');
foreach(['<a href="mailto:hello@invented.test">Write</a>','<a href="tel:+77770000000">Call</a>','<a href="https://invented.test">Site</a>','<p>hello&#64;invented.test</p>','<p>https://invented.test</p>','<p>+7 (777) 000-00-00</p>'] as $html)contactReject($html,'Pottery workshop without supplied contacts.');
contactReject('<a href="https://example.test/book-evil">Site</a>',$brief);
contactReject('<a href="mailto:hello@invented.test">Write</a>','Do not use imaginary contact details.');
final class ContactProvider implements StructuredAiProvider {
 public int $calls=0;
 public function __construct(private array $responses){}
 public function completeJson(string $s,string $u,array $schema,string $name,int $maxTokens=3200):array{$this->calls++;return ['content'=>json_encode(array_shift($this->responses),JSON_THROW_ON_ERROR),'model'=>'test/model'];}
}
$design=['siteType'=>'Workshop','audience'=>'Adults','goal'=>'Book','style'=>'Clay','html'=>'<section class="ai-hero"><h1>Workshop</h1><a href="mailto:hello@invented.test">Book</a></section><section class="ai-formats"><h2>Formats</h2></section><section class="ai-booking" id="booking"><h2>Booking</h2></section>','css'=>'.ai-hero{padding:40px}'];
$fixed=$design;$fixed['html']=str_replace('mailto:hello@invented.test','#booking',$fixed['html']);
$provider=new ContactProvider([$design,$fixed]);$result=AiGeneratedWebsite::create('Workshop without supplied contacts.',$provider);
contactCheck($provider->calls===2&&$result['metadata']['repairAttempted']&&$result['design']['contactAudit']['status']==='passed','Invented contacts trigger exactly one validated repair');
$bad=new ContactProvider([$design,$design]);
try{AiGeneratedWebsite::create('Workshop',$bad);throw new RuntimeException('Expected rejected repair');}catch(AiProviderFailure $error){contactCheck($bad->calls===2&&$error->category==='contact_requirements','Repeated invented contacts fail closed');}
