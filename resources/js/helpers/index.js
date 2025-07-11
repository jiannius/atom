import alert from './alert'
import toast from './toast'
import modal from './modal'
import empty from './empty'
import confirm from './confirm'
import darkmode from './darkmode'
import floatingui from './floatingui'

export default {
    alert,
    toast,
    modal,
    empty,
    confirm,
    darkmode,
    floatingui,

    json: (data) => JSON.stringify(data, null, 2),
    random: () => Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15),
}