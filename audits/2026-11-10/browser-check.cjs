const {chromium,devices}=require('playwright');
const fs=require('node:fs');const assert=require('node:assert/strict');
const path=require('node:path');
const script=fs.readFileSync(path.join(__dirname,'../../t-rent-booking-health/assets/booking-health.js'),'utf8');
const output=process.env.AUDIT_OUTPUT_DIR||path.join(__dirname,'local-results');
fs.mkdirSync(output,{recursive:true});
const product='https://t-rent.no/product/stubbefres-proff-tenner/';
const results=[];
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true,args:['--no-sandbox'],...((process.env.HTTPS_PROXY||process.env.HTTP_PROXY)?{proxy:{server:process.env.HTTPS_PROXY||process.env.HTTP_PROXY}}:{})});
 try {
  for(const test of [
   {name:'desktop-stale-recovery',stale:true},
   {name:'mobile-stale-recovery',stale:true,mobile:true},
   {name:'fresh-no-extra-request'},
   {name:'cached-refresh-rejected',stale:true,badMarker:true},
   {name:'single-retry-limit',stale:true,alwaysReject:true},
   {name:'restored-tab-refreshes',resume:true},
   {name:'quote-refreshed-once',quote:true},
   {name:'quote-rejection-not-replayed',quote:true,quoteReject:true},
   {name:'quote-stops-if-refresh-fails',quote:true,badMarker:true},
  ]) {
   const context=await browser.newContext(test.mobile?{...devices['iPhone 13']}:{});
   const events={name:test.name,calculations:0,snapshots:0,quotes:0,errors:[],quoteBodies:[]};
   let latestLinked=null,latestQuote=null;
   await context.route('**/product/stubbefres-proff-tenner/**',async route=>{
    if(route.request().method()!=='GET')return route.continue();
    const url=new URL(route.request().url());const id=url.searchParams.get('_trent_booking_refresh');
    if(id)events.snapshots++;
    const response=await route.fetch();let html=await response.text();
    const conf={version:'1.0.0',ajaxUrl:'https://t-rent.no/wp-admin/admin-ajax.php',productUrl:product,generatedAt:Math.floor(Date.now()/1000),refreshId:id?(test.badMarker?'wrong-marker':id):'',maxAgeMs:300000};
    html=html.replace('</body>',`<script>var TRentBookingHealth = ${JSON.stringify(conf)};</script><script>${script}</script></body>`);
    html=html.replace('<script id="wcpay-frontend-tracks-js"',`<script>window.wcpayAssets=window.wcpayAssets||{url:'https://t-rent.no/wp-content/plugins/woocommerce-payments/dist/'};</script><script id="wcpay-frontend-tracks-js"`);
    if(id){latestLinked=html.match(/name="trent_linked_booking_nonce" value="([^"]+)"/)?.[1];latestQuote=JSON.parse(html.match(/var RFQ_DATA = (\{.*?\});/s)[1]).nonce;}
    await route.fulfill({response,body:html});
   });
   if(test.stale)await context.route('**/assets/js/main-script.js*',async route=>{const response=await route.fetch();await route.fulfill({response,body:(await response.text()).replace('const nonce = CALENDAR_DATA.nonce;',"const nonce = 'expired-test-nonce';")})});
   await context.route('**/wp-admin/admin-ajax.php',async route=>{
    const params=new URLSearchParams(route.request().postData()||'');const action=params.get('action');
    if(action==='rnb_calculate_inventory_data'){
     events.calculations++;
     if(test.alwaysReject)return route.fulfill({json:{error:['Nonce-verdien kan ikke verifiseres']}});
    }
    if(action==='redq_request_for_a_quote'){
     events.quotes++;events.quoteBodies.push(Object.fromEntries(params));
     return route.fulfill({json:test.quoteReject?{success:false,message:'Nonce-verdien kan ikke verifiseres'}:{status_code:200,message:'Mock accepted'}});
    }
    await route.continue();
   });
   const page=await context.newPage();page.on('pageerror',e=>events.errors.push(e.message));
   await page.goto(product,{waitUntil:'load',timeout:45000});
   await page.waitForFunction(()=>window.jQuery&&window.CALENDAR_DATA&&window.RNB_HELPER?.rnbProcessFormData);
   if(test.resume)await page.evaluate(()=>dispatchEvent(new PageTransitionEvent('pageshow',{persisted:true})));
   if(test.quote){
    const response=await page.evaluate(()=>new Promise(resolve=>jQuery.ajax({url:CALENDAR_DATA.ajax_url,type:'POST',dataType:'json',data:{action:'redq_request_for_a_quote',nonce:'old-quote-token',product_id:494,form_data:[{name:'trent_linked_booking_nonce',value:'old-linked-token'},{name:'pickup_date',value:'11/17/2026'}]},success:resolve})));
    assert.equal(events.snapshots,1,'quote preflight snapshot count');
    assert.equal(events.quotes,test.badMarker?0:1,'never replay or send after failed preflight');
    if(!test.badMarker){
     assert.equal(events.quoteBodies[0].nonce,latestQuote,'quote token updated');
     assert.equal(events.quoteBodies[0]['form_data[0][value]'],latestLinked,'linked token updated');
     assert.equal(events.quoteBodies[0]['form_data[1][value]'],'11/17/2026','date preserved');
    }
    if(test.quoteReject)assert.match(response.message,/Nonce/);
   }else{
    await page.evaluate(()=>{jQuery('#pickup-date').val('11/17/2026');jQuery('#pickup-time').val('21:00');jQuery('#dropoff-date').val('11/18/2026');jQuery('#dropoff-time').val('13:00').trigger('change')});
    const fails=test.badMarker||test.alwaysReject;
    await page.waitForFunction(fails=>fails?document.querySelector('.rnb-error-message'):!document.querySelector('.redq_request_for_a_quote').disabled,fails,{timeout:35000});
    assert.equal(events.calculations,test.stale&&!test.badMarker?2:1,'bounded calculation attempts');
    assert.equal(events.snapshots,test.stale||test.resume?1:0,'refresh only when needed');
    assert.equal(await page.inputValue('#pickup-date'),'11/17/2026','pickup date retained');
    assert.equal(await page.inputValue('#pickup-time'),'21:00','pickup time retained');
    assert.equal(await page.inputValue('#dropoff-date'),'11/18/2026','return date retained');
    if(!fails)assert.equal(await page.locator('.rnb-error-message').count(),0,'no visible nonce error');
    if(test.mobile)await page.screenshot({path:path.join(output,'recovery-mobile.png'),fullPage:true});
   }
   assert.deepEqual(events.errors,[],'no browser exceptions, including WooPayments');
   results.push(events);console.log('PASS',test.name,JSON.stringify({calculations:events.calculations,snapshots:events.snapshots,quotes:events.quotes}));
   await context.close();
  }
 }finally{fs.writeFileSync(path.join(output,'recovery-tests.json'),JSON.stringify(results,null,2));await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1});
