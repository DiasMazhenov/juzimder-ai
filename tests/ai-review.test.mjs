import test from 'node:test';
import assert from 'node:assert/strict';
import { hasAcceptedDesignReview } from '../src/ai-review.js';
test('all profiles require explicit facts and contrast acceptance',()=>{
 for(const profile of ['standard','taste','user_skill']){
  assert.equal(hasAcceptedDesignReview(profile,{}),false);
  assert.equal(hasAcceptedDesignReview(profile,{facts:true}),false);
 }
 assert.equal(hasAcceptedDesignReview('standard',{facts:true,contrast:true}),true);
 assert.equal(hasAcceptedDesignReview('unknown',{facts:true,contrast:true}),false);
});
test('profile review cannot bypass common acceptance or viewport visits',()=>{
 const all={facts:true,contrast:true,concept:true,content:true,visited:{desktop:true,tablet:true,mobile:true},viewports:{desktop:true,tablet:true,mobile:true},manualReview:[true,true]};
 assert.equal(hasAcceptedDesignReview('taste',all),true);
 assert.equal(hasAcceptedDesignReview('taste',{...all,visited:{desktop:true,tablet:true,mobile:false}}),false);
 assert.equal(hasAcceptedDesignReview('user_skill',all),true);
 assert.equal(hasAcceptedDesignReview('user_skill',{...all,manualReview:[true,false]}),false);
 assert.equal(hasAcceptedDesignReview('user_skill',{...all,facts:false}),false);
 assert.equal(hasAcceptedDesignReview('user_skill',{...all,manualReview:[]}),false);
});
