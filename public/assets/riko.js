document.querySelectorAll('[data-copy]').forEach(button=>button.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(button.dataset.copy);const old=button.innerHTML;button.textContent='Copied';setTimeout(()=>button.innerHTML=old,1800)}catch{button.textContent='Select and copy the code'}}));
document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',event=>{if(!window.confirm(form.dataset.confirm))event.preventDefault()}));
document.querySelectorAll('[data-filter]').forEach(input=>input.addEventListener('input',()=>{document.querySelectorAll('[data-filter-row]').forEach(row=>row.hidden=!row.textContent.toLowerCase().includes(input.value.toLowerCase()))}));
document.querySelectorAll('[data-fee-form]').forEach(form => {
  const output = form.querySelector('[data-fee-output]');
  let timer, version = 0;
  const refresh = () => {
    clearTimeout(timer);
    if(form.dataset.manual) { form.elements.quote_token.value=''; form.querySelector('[data-manual-submit]').disabled=true; }
    const current = ++version;
    output.textContent = 'Calculating fees…';
    timer = setTimeout(async () => {
      const data = new FormData(form);
      if (!data.get('amount')) { output.textContent = 'Enter an amount to see fees and net credit.'; return; }
      try {
        const response = await fetch('/actions/fee-quote', {method:'POST', headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':data.get('_token')}, body:JSON.stringify({direction:form.dataset.feeForm,method_id:data.get('method_id'),asset:data.get('asset'),amount:data.get('amount')})});
        const body = await response.json();
        if(current !== version) return;
        if(!response.ok) throw new Error(Object.values(body.errors || {}).flat()[0] || body.message || 'Unable to calculate fees.');
        const q = body.result;
        if(form.dataset.manual) { form.elements.quote_token.value=q.quote_token; form.querySelector('[data-manual-submit]').disabled=false; }
        output.replaceChildren();
        const heading=document.createElement('h3'); heading.textContent='Estimated breakdown'; output.append(heading);
        if(q.source_asset) {const info=document.createElement('p'); info.textContent=`Hold: ${q.source_amount} RC · 1 RC ≈ ${q.rate} ${q.asset}`; output.append(info);}
        for(const [label,value] of [[q.source_asset?'Converted amount':(q.direction==='deposit'?'You pay':'Wallet deduction'),q.gross],['Processing fee',q.processing_fee],['Platform fee',q.platform_fee],[`Tax on fees (${q.tax_percent}%)`,q.tax],['Total fees',q.total_fee],[q.source_asset?'Approximate payout':(q.direction==='deposit'?'Net wallet credit':'Net bank credit'),q.net]]) {
          const row=document.createElement('p'); row.className='helper gap-top'; row.textContent=`${label}: ${value} ${q.asset}`; output.append(row);
        }
        const note=document.createElement('p'); note.className='helper gap-top'; note.textContent=q.message; output.append(note);
      } catch(e) { if(current===version) output.textContent=e.message; }
    }, 400);
  };
  for(const name of ['amount','method_id','asset']) form.elements[name].addEventListener('input',refresh);
  if(form.dataset.manual) form.addEventListener('submit',event=>{if(!form.elements.quote_token.value || !window.confirm('Submit this withdrawal using the displayed rate and fees? Your RikoCoin will be held for manual payout.')) event.preventDefault();});
});
