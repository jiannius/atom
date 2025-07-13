import ajax from './ajax'
import alert from './alert'
import toast from './toast'
import modal from './modal'
import empty from './empty'
import confirm from './confirm'
import floatingui from './floatingui'

export default {
    alert,
    toast,
    modal,
    empty,
    confirm,
    floatingui,
    
    ajax: (url, headers = null) => new ajax(url, headers),
    json: (data) => JSON.stringify(data, null, 2),
    action: (name, payload) => new ajax('/atom/action/'+name).post(payload),
    random: () => Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15),
}