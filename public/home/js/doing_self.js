/**
 * cfc 写的js
 */




//器械健身取消预约的跳转事件
function link_cancel_appoint(){
	var starttime = $('#hid_starttime').val();
	var id = $("#hid_ap_id").val();
	$.ajax({
		url : './index.php?m=Home&c=Index&a=check_cancel',
		type : 'POST',
		data : { "start_time" : starttime },
		success : function(rdata){
			if(rdata['status'] == 1){
				window.location.href = "./index.php?m=Home&c=Index&a=appointment_cancel&id="+id;
			}else if(rdata['status'] == 2){
				layer.msg(rdata['msg']);
			}
		},
		error : function(){
			layer.msg("操作失败");
		}
	});
}

//器械健身的取消预约
//function cancel_appointment(){
//	layer.msg('确认取消预约？',{
//		time : 0,
//		btn : ['确认','取消'],
//		yes : function(index){
//			layer.close(index);
//			show_loading();
//			var starttime = $('#hid_starttime').val();
//			var id = $("#hid_ap_id").val();
//			$.ajax({
//				url : './index.php?m=Home&c=Index&a=cancel_shop_appointment',
//				type : 'POST',
//				data : {
//					"id" : id,
//					"start_time" : starttime
//				},
//				success : function(rdata){
//					hide_loading();
//					if(rdata['status'] == 1){
//						layer.msg(rdata['msg'],{
//							time : 1000
//						},function(){
//							window.location.href = "./index.php?m=Home&c=Index&a=appointment_detail&id="+id;
//						});
//					}else if(rdata['status'] == 2){
//						layer.msg(rdata['msg']);
//					}
//				},
//				error : function(){
//					layer.msg("操作失败");
//				}
//			});
//		}
//	});
//}

//团操列表页  预约的点击事件  
function action_link(obj){
	var url = $(obj).attr('data-url');
	$.ajax({
		url : './index.php?m=Home&c=Action&a=check_vip_status',
		type : 'POST',
		data : {},
		success : function(rdata){
			if(rdata['status'] == 1){
				window.location.href = url;
			}else if(rdata['status'] == 0){
				layer.msg(rdata['msg']);
				return false;
			}
		},
		error : function(rdata){
			layer.msg("操作失败");
		}
	});
}


//团操取消预约的跳转事件
function link_cancel_appoint_action(){
	show_loading();
	var starttime = $('#hid_starttime').val();
	var id = $("#hid_ap_id").val();
	$.ajax({
		url : './index.php?m=Home&c=Action&a=check_cancel',
		type : 'POST',
		data : { "start_time" : starttime },
		success : function(rdata){
			hide_loading();
			if(rdata['status'] == 1){
				window.location.href = "./index.php?m=Home&c=Action&a=appointment_cancel&id="+id;
			}else if(rdata['status'] == 2){
				layer.msg(rdata['msg']);
			}
		},
		error : function(){
			hide_loading();
			layer.msg("操作失败");
		}
	});
}

//我的预约----取消预约的跳转事件
function link_cancel_appoint_action(c_starttime,obj,type){
	show_loading();
	var starttime = c_starttime;
	var url = $(obj).attr('data-url');
	$.ajax({
		url : './index.php?m=Home&c=Action&a=check_cancel',
		type : 'POST',
		data : { 
			"start_time" : starttime,
			'type' : type
		},
		success : function(rdata){
			hide_loading();
			if(rdata['status'] == 1){
				window.location.href = url;
			}else if(rdata['status'] == 2){
				layer.msg(rdata['msg']);
			}
		},
		error : function(){
			hide_loading();
			layer.msg("操作失败");
		}
	});
}

//团操的取消预约
function cancel_appointment_action(){
	layer.msg('确认取消预约？',{
		time : 0,
		btn : ['确认','取消'],
		yes : function(index){
			layer.close(index);
			show_loading();
			var starttime = $('#hid_starttime').val();
			var id = $("#hid_ap_id").val();
			$.ajax({
				url : './index.php?m=Home&c=Action&a=cancel_action_appointment',
				type : 'POST',
				data : {
					"id" : id,
					"start_time" : starttime
				},
				success : function(rdata){
					hide_loading();
					if(rdata['status'] == 1){
						layer.msg(rdata['msg'],{
							time : 1000
						},function(){
							window.location.href = "./index.php?m=Home&c=Action&a=appointment_detail&id="+id;
						});
					}else if(rdata['status'] == 2){
						layer.msg(rdata['msg']);
					}
				},
				error : function(){
					layer.msg("操作失败");
				}
			});
		}
	});
}

//提交对团操的评价
function sub_action_evaluate(){
	var action_appointment_id = $("#hid_action_appointment_id").val();
	var content = $("textarea[name='content']").val();
	var point = $("input[name='hid_end_point']").val();
	var type = 2;
	if( point == 0 ){
		layer.msg("请对本次团操评分，谢谢");
		return false;
	}
	
	$.ajax({
		url : './index.php?m=Home&c=Action&a=save_action_evaluate',
		type : 'POST',
		data : {
			"apid" : action_appointment_id,
			"content" : content,
			"point" : point,
			"type" : type
		},
		success : function(rdata){
			if(rdata['status'] == 1){
				layer.msg(rdata['msg'],{
					time : 1000
				},function(){
					window.location.href = "./index.php?m=Home&c=Appointment&a=index"
				});
			}else if(rdata['status'] == 2){
				layer.msg(rdata['msg']);
			}
		},
		error : function(){
			layer.msg("操作失败");
		}
	});
}





























