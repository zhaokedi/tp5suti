jQuery(document).ready(function($) {
	
	$.extend({
		allControl:function(){
			var b = 1;
			$(".listBlock input[type='checkbox']").each(function() {
				if($(this).is(":checked"))
				{
					var a = 1;
				}
				else
				{
					var a = 0;
				}
				b = Math.min(a,b);
			});
			if(b == 1)
			{
				$("#cb0").prop("checked",true);
			}
			else
			{
				$("#cb0").prop("checked",false);
			}
		}
	});
	
	$.extend({
		sum:function(){
			var sum = new Number;
			sum = 0;
			var account = 0;
			$(".listBlock input[type='checkbox']").each(function() {
				if($(this).is(":checked"))
				{
					var a = $(this).parents(".listBlock").find(".text .price").text();
					var b = $(this).parents(".listBlock").find(".text_box").val();
					sum += (a * parseInt(b));
				}
			});
			
			$(".priceAll").text(Math.round(sum*100)/100);
		}
	});
	
	$("#cb0").click(function(){
		if($(this).is(":checked"))
		{
			$(".listBlock input[type='checkbox']").prop("checked",true);
		}
		else
		{
			$(".listBlock input[type='checkbox']").prop("checked",false);
		}
	});
	
	$(".listBlock input[type='checkbox']").click(function(){
		$.allControl();
	});
	
	$("input").click(function(){
		$.sum();
	});
	
	$(".min").click(function(){
		var t=$(this).parent().find(".text_box"); 
		t.val(parseInt(t.val())-1);
		if(parseInt(t.val())<1){ 
			t.val(1);
		}
		$.sum();
	});
	
	$(".add").click(function(){ 
		var t=$(this).parent().find(".text_box"); 
		t.val(parseInt(t.val())+1);
		$.sum();
	});
	
	$(".text_box").change(function(){
		if(parseInt($(this).val())<1 || $(this).val().length==0){ 
			$(this).val(1);
		}
		//alert($(this).val().length);
		$.sum();
	});
	
});