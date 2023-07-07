let files = new FormData()
let iter = 0;

function multifileDescs()
{
    if(blob.files.length != 0) {

        for(let i = 0; i < blob.files.length; i++) {

            files.append("blob[]", blob.files[i])

            filesDescs.insertAdjacentHTML("beforeend", `
                <tr>
                    <td width="120" valign="top">
                        <img width="125" src="${window.URL.createObjectURL(blob.files[i])}">
                    </td>
                    <td>
                        <span>${blob.files[i].name}</span>
                        <br>
                        <textarea style="margin: 0px; height: 50px; width: 259px; resize: none;" name="desc_${iter}"></textarea>
                    </td>
                </tr>
            `)

            iter+=1

        }

        uploadButton.removeAttribute("disabled")
    }

}

function uploadPictures(album)
{
    uploadButton.setAttribute("disabled", "disabled")

    if(files.get("hash") == null) {
        files.append("hash", u("meta[name=csrf]").attr("value"))
        files.append("album", album)
    }

    for(let i = 0; i < iter; i++) {
        files.append("desc_"+i, document.querySelector("textarea[name=desc_"+i+"]").value)
    }
    
    let xhr = new XMLHttpRequest()
    xhr.open("POST", "/photos/upload?album="+album)
    xhr.onload = () => {
        let result = JSON.parse(xhr.responseText)

        if(result.error != undefined) {
            ajaxError(tr(result.error + "_error"), tr(result.error + "_error_desc"), "fail")
        } else {
            ajaxError(tr(result.success_msg), tr(result.success_msg + "_desc"), "succ")

            for(const el of document.querySelectorAll("td > img")) {
                console.log(el.src)
                window.URL.revokeObjectURL(el.src)
            }

            files.delete("blob[]")
            
            for(let i = 0; i < iter; i++) {
                files.delete("desc_"+i)
            }

            uploadButton.setAttribute("disabled", "disabled")
            filesDescs.innerHTML = ``
            iter = 0;

        }

    }
    xhr.send(files)
}

function deletePhoto(photo, album)
{
    let body = `
    ${tr('sure_deleting_photo')}<br>
    <input type="checkbox" id="fully_delete" value="1">${tr("also_delete")}
    `

    MessageBox(tr('deleting_from_album'), body, [
        tr('yes'),
        tr('no')
    ], [
        (function() {
            let xhr = new XMLHttpRequest()
            xhr.open("POST", `/album${album}/remove_photo/${photo}`)
            xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            xhr.onload = () => {
                let result = JSON.parse(xhr.responseText)

                if(result.error != undefined) {
                    ajaxError(tr(result.error + "_error"), tr(result.error + "_error_desc"), "fail")
                } else {
                    // ajaxError(tr(result.success_msg), tr(result.success_msg + "_desc"), "succ")

                    let dsc = document.querySelector("#photos_count")
                    dsc.innerHTML = result.newCount + " " + tr("photos")

                    document.querySelector("#photo"+album+"_"+photo).parentNode.removeChild(document.querySelector("#photo"+album+"_"+photo))
                    
                    if(document.querySelectorAll(".album-photo").length == 0) {
                        location.reload()
                    }
                }
            }

            xhr.send("hash="+u("meta[name=csrf]").attr("value")+"&fully_delete="+fully_delete.checked)
        }),
        (function() {
            u("#tmpPhDelF").remove();
        }),
    ]);
}

async function editAlbum(album)
{
    let info = await API.Photos.getAlbumInfo(album)
    // console.log(info)

    let body = `
        ${tr("name")}:
        <input type="text" name="name" value="${info.name}" /><br><br>
        ${tr("description")}:<br>
        <textarea style="margin: 0px; height: 50px; width: 159px; resize: none;" name="desc">${info.description}</textarea><br><br>
        <input onclick="deleteAlbum(${info.owner}, ${info.id})" class="button" type="button" value="${tr("delete_album")}">
        `

    MessageBox(tr('change_album'), body, [
        tr('save'),
        tr('cancel')
    ], [
        (async function() {
            let name = document.querySelector("input[name=name]").value
            let desc = document.querySelector("textarea[name=desc]").value

            let res = await API.Photos.editAlbum(album, name, desc)
            
            albumName.innerHTML = res.name
        }),
        (function() {
            u("#tmpPhDelF").remove();
        }),
    ]);
}

function deleteAlbum(owner, album)
{
    u("body").removeClass("dimmed");
    u(".ovk-diag-cont").remove();

    let body = `
        ${tr("sure_deleting_album")}
    `

    MessageBox(tr('delete_album'), body, [
        tr('yes'),
        tr('no')
    ], [
        (async function() {
            let xhr = new XMLHttpRequest()
            xhr.open("POST", "/album"+owner+"_"+album+"/delete")
            xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');

            xhr.onload = () => {
                location.href = "/albums"+owner
            }

            xhr.send("hash="+u("meta[name=csrf]").attr("value"))
        }),
        (function() {
            u("#tmpPhDelF").remove();
        }),
    ]);
}