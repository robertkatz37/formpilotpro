jQuery(function($){
 $(document).on('click','.fp-test-btn',function(){
  const b=$(this), card=b.closest('.fp-int-card'), result=card.find('.fp-test-result');
  b.prop('disabled',true).text('Testing…'); result.removeClass('ok error').text('');
  $.post(window.ajaxurl,{action:'fp_test_integration',provider:b.data('provider'),nonce:window.fpIntegrationsNonce})
   .done(function(r){result.addClass(r.success?'ok':'error').text(r.data&&r.data.message?r.data.message:'Connection test completed.');})
   .fail(function(xhr){let msg='Connection test failed.';try{msg=xhr.responseJSON.data.message||msg;}catch(e){}result.addClass('error').text(msg);})
   .always(function(){b.prop('disabled',false).text('Test connection');});
 });
});
