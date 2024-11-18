$(document).on("click", "#addToFaves", (e) => {
    let xhr = new XMLHttpRequest

    xhr.open("POST", "/fave/add")
    xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');

    xhr.onloadstart = () => {
        e.currentTarget.insertAdjacentHTML("beforeend", `
            <img id="loader" src="/assets/packages/static/openvk/img/loading_mini.gif">
        `)

        e.currentTarget.style.pointerEvents = "none"
    }

    xhr.onerror = () => {onerror()}

    xhr.onload = () => {
        let result = JSON.parse(xhr.responseText)

        if(result.error != "no") {
            MessageBox(tr("error"), result.error, [tr("ok")], [() => {Function.noop}])
            u("#loader").remove()
        } else {
            if(result.action == "faved") {
                e.currentTarget.innerHTML = tr("remove_from_faves")
            } else {
                e.currentTarget.innerHTML = tr("add_to_faves")
            }
        }

        e.currentTarget.style.pointerEvents = "all"
    }

    xhr.send("hash="+u("meta[name=csrf]").attr("value")+
            "&model="+e.currentTarget.dataset.type+
            "&target="+e.currentTarget.dataset.id)
})