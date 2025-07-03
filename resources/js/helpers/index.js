import alert from './alert'
import toast from './toast'
import modal from './modal'
import confirm from './confirm'
import darkmode from './darkmode'
import floatingui from './floatingui'

export default {
    alert,
    toast,
    modal,
    confirm,
    darkmode,
    floatingui,

    json: (data) => JSON.stringify(data, null, 2),
}