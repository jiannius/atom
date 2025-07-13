export default class 
{
    url
    headers

    constructor (url, headers = {}) {
        this.url = url
        this.headers = {
            ...headers,
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    }

    get (payload) {
        let url = !empty(payload)
            ? this.url+'?'+new URLSearchParams(payload).toString()
            : this.url

        return fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json', 
                ...this.headers,
            },
        }).then(res => (res.json()))
    }

    post (payload) {
        return fetch(this.url, {
            method: 'POST',
            body: payload instanceof FormData ? payload : JSON.stringify(payload),
            headers: payload instanceof FormData ? this.headers : {
                'Content-Type': 'application/json',
                ...this.headers,
            },
        }).then(res => (res.json()))
    }
}