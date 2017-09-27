/**
 * 自己写的验证js
 */



/*******  团操的添加事件   *******/
function action_check(){
	//教练名称
	var teacher_id = $("input[name='teacher_id']").val();
	//教练头像
	var teacherhead = '';
	$(".add_btn_teacher").parent().find(".add_photos").each(function(){
		var src = $(this).find('img').attr('src');
		teacherhead = src;
	});
	//团操主题
	var actionname = $("input[name='actionname']").val();
	//主题说明
	var actionabout = $("input[name='actionabout']").val();

	//强度
	var powerlevel = $("select[name='powerlevel']").val();
	//所属城市
	var city_id = $("select[name='city_id']").val();
	var shop_id = $("select[name='shop_id']").val();

	//地址
	var address =$("#address").val();
	//经纬度
	var lat = $("#lat").val();

	//容纳人数
	var limit_people = $("input[name='limit_people']").val();

	//上课日期
	var teachdate = $("input[name='teachdate']").val();
	//开始时间
	var starttime = $("input[name='starttime']").val();
	//结束时间
	var endtime = $("input[name='endtime']").val();


	if( teacher_id == '' ){
		layer.msg('请选择教练');
		return;
	}

	if( actionname == '' ){
		layer.msg('请填写团操主题');
		return;
	}

	if( actionabout == '' ){
		layer.msg('请填写主题说明');
		return;
	}

	if( limit_people == '' ){
		layer.msg('请填写活动人数');
		return;
	}
	
	//if( teachdate == '' ){
	//	layer.msg('请选择上课日期');
	//	return false;
	//}
	
	if( starttime == '' ){
		layer.msg('请选择开始时间');
		return;
	}
	
	if( endtime == '' ){
		layer.msg('请选择结束时间');
		return;
	}
	$('#signupForm').submit();
	//return true;

}

/*******  团操的添加事件   *******/
function activ_check(){


	//团操主题
	var activname = $("input[name='activname']").val();
	//主题说明
	var actionabout = $("input[name='actionabout']").val();

	//强度
	var powerlevel = $("select[name='powerlevel']").val();


	//地址
	var address =$("#address").val();
	//经纬度
	var lat = $("#lat").val();


	//容纳人数
	var limit_people = $("input[name='limit_people']").val();
	var waiter_id = $("select[name='waiter_id']").val();
	//上课日期
	var teachdate = $("input[name='teachdate']").val();
	//开始时间
	var starttime = $("input[name='starttime']").val();
	//结束时间
	var endtime = $("input[name='endtime']").val();
	if( waiter_id == 0 ){
		layer.msg('请选择服务员');
		return;
	}

	if( activname == '' ){
		layer.msg('请填写活动主题');
		return;
	}

	//if( actionabout == '' ){
	//	layer.msg('请填写主题说明');
	//	return;
	//}

	if( limit_people == '' ){
		layer.msg('请填写活动人数');
		return;
	}
	if( starttime == '' ){
		layer.msg('请选择开始时间');
		return;
	}

	if( endtime == '' ){
		layer.msg('请选择结束时间');
		return;
	}
	$('#signupForm').submit();
	//return true;

}

/*******  会员 会员卡变更的验证事件   *******/
function card_check(){
	console.log(1);
	return false;
	//来源
	var source = $("select[name='source']").val();
	var source2 = $("input[name='source2']").val();
	//return false;
	if( source == 0 ){

		layer.msg('请选择开卡来源');
		return false;
	}else if(source==1){
		if(source2==''){
			layer.msg('请填写开卡来源');
			return false;
		}

	}




	//$('#signupForm').submit();
	//return true;

}

/***** 地图拾取事件  *****/
function pic_lat(){
	// 百度地图API功能
	var map = $map;
	var p = $("#location_p").val();
	var s = $("#location_c").val();
	var d = $("#location_a").val();
	var j = $("#detail_address").val();
	var str = p+s+d+j;
	map.centerAndZoom(str,13);
	//单击获取点击的经纬度
	map.addEventListener("click",function(e){
		layer.confirm("确认拾取的坐标是：" + e.point.lng + "," + e.point.lat,function(){
			$("#lat").val(e.point.lng + "," + e.point.lat);
			layer.closeAll();
		})
	});

	layer.open({
		type: 1,
		title: false,
		closeBtn: true,
		area: ['800px','500px'],
		skin: 'white', //没有背景色
		shadeClose: true,
		content: $('#allmap'),
		cancel : function(){
			//map.destroy();
			layer.closeAll();
		}
	});
}










