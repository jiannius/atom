import ajax from './ajax'
import alert from './alert'
import toast from './toast'
import modal from './modal'
import command from './command'
import empty from './empty'
import arr from './arr'
import str from './str'
import currency from './currency'
import confirm from './confirm'
import floatingui from './floatingui'
import recaptcha from './recaptcha'
import safeUrl from './safe-url'
import toastLift from './toast-lift'

export default {
    alert,
    toast,
    modal,
    command,
    empty,
    arr,
    str,
    currency,
    confirm,
    floatingui,
    recaptcha,
    safeUrl,
    toastLift,

    ajax: (url, headers = null) => new ajax(url, headers),
    json: (data) => JSON.stringify(data, null, 2),
    action: (name, payload) => new ajax('/atom/action/'+name).post(payload),
    random: () => Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15),
}