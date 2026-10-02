// Teste do comportamento do formulário sem dependências externas.
const fs=require('fs'),vm=require('vm'),assert=require('assert');
class Element {
 constructor(){this.value='';this.hidden=false;this.textContent='';this.events={};this.attrs={};}
 addEventListener(name,fn){this.events[name]=fn;}
 removeAttribute(name){delete this.attrs[name];}
 set src(value){this.attrs.src=value;} get src(){return this.attrs.src;}
 fire(name){return this.events[name]?.();}
}
const names=['data','inicio','fim','forma_pagamento','cupom','pix-box','pix-valor','pix-qrcode','resumo-valor','aplicar-cupom','csrf'];
const els=Object.fromEntries(names.map(n=>[n,new Element()]));els.csrf.value='csrf-teste';
let mode='discount',body;
const document={getElementById:id=>els[id],querySelector:s=>s.includes('horario_inicio')?els.inicio:s.includes('horario_fim')?els.fim:els.csrf};
const context={document,Date,Intl,URLSearchParams,fetch:async(url,options)=>{body=options.body;return mode==='error'?{ok:false,json:async()=>({erro:'Cupom inválido'})}:{ok:true,json:async()=>({original:240,desconto:mode==='discount'?24:0,total:mode==='discount'?216:240})};}};
vm.runInNewContext(fs.readFileSync(__dirname+'/../reserva.js','utf8'),context);
(async()=>{
 assert(els['pix-box'].hidden,'Pix oculto inicialmente');
 els.data.value='2026-12-15';els.inicio.value='10:00';els.fim.value='12:00';els.cupom.value='REDSTAR10';
 await els['aplicar-cupom'].fire('click');assert.equal(body.get('cupom'),'REDSTAR10');assert.equal(body.get('csrf_token'),'csrf-teste');assert(els['resumo-valor'].textContent.includes('216,00'));
 els.forma_pagamento.value='Pix';await els.forma_pagamento.fire('change');assert(!els['pix-box'].hidden);const payload=new URL(els['pix-qrcode'].src).searchParams.get('data');assert(payload.includes('5406216.00'),'QR usa valor final com desconto');assert(els['pix-valor'].textContent.includes('216,00'));
 els.forma_pagamento.value='Dinheiro';await els.forma_pagamento.fire('change');assert(els['pix-box'].hidden);assert.equal(els['pix-qrcode'].src,undefined);
 els.forma_pagamento.value='Pix';await els.forma_pagamento.fire('change');els.cupom.value='OUTRO';els.cupom.fire('input');assert(els['pix-qrcode'].hidden);assert.equal(els['pix-qrcode'].src,undefined,'Alteração de cupom invalida QR anterior');
 mode='error';await els['aplicar-cupom'].fire('click');assert(els['pix-qrcode'].hidden);assert.equal(els['resumo-valor'].textContent,'Cupom inválido');
 mode='full';els.cupom.value='';await els['aplicar-cupom'].fire('click');assert(els['resumo-valor'].textContent.includes('240,00'));assert(new URL(els['pix-qrcode'].src).searchParams.get('data').includes('5406240.00'));
 console.log('Pix: ocultação, aplicação de cupom, valor final, invalidação de QR e erro de servidor passaram.');
})().catch(e=>{console.error(e);process.exit(1)});
