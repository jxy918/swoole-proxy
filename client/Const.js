/** 
 * 主命令字定义，（可以有多个主命令字，每个主命令字对应一个子命令字） 
 */
var MainCmdID = {
    CMD_SYS               :   1, /** 系统类（主命令字）- 客户端使用 **/
}//MainCmdID


/** 
 *子命令字定义，h5客户端也应有一份对应配置，REQ结尾一般是客户端请求过来的子命令字， RESP服务器返回给客户端处理子命令字
 */
var SUB_CMD_SYS = {
    SEND_BINARY_REQ : 1, 
	HEART_BEAT_ASK_REQ : 2,  //心跳
}//SUB_CMD_SYS
