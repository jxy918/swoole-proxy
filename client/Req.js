/**发请求命令字处理类*/

var Req = {
    //定时器
    timer : 0,
    
    //发送心跳        
    heartBeat:function(obj) {     
        this.timer = setInterval(function () { 
            if(obj.ws.readyState == obj.ws.OPEN) {
                var data = {};           
                data['data'] = '心跳:'+(new Date()).valueOf()       
                obj.send(data, MainCmdID.CMD_SYS, SUB_CMD_SYS.HEART_BEAT_ASK_REQ);
            } else {
                clearInterval(this.timer);
            }
        },30000);         
    },
		
    //发送数据
    SendBinary: function(obj, data) {
        var data = {data};  			
        obj.send(data, MainCmdID.CMD_SYS, SUB_CMD_SYS.SEND_BINARY_REQ);
    },

}