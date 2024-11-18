async function displayFriendsListCreation(user)
{
    let count    = await API.Friends.getFriendsListsCount(user);
    let maxCount = await API.Friends.getMaxListsCount();

    let friends = await API.Friends.getFriends()

    let body = ``
    if(count < maxCount.count) {
        let colors = [
            "#E5EED9", "#F5E9E2", "#c9422a", "#4a1eba", "#13547d", "#15ad45", "#d1d1d1"
        ]

        let color = colors[Math.floor(Math.random() * colors.length)]
        body = `
        <p><b>${tr("l_name")}:</b></p>
        <input type="text" name="nami" required minlength="1">
        <p><b>${tr("l_color")}:</b></p>
        <input type="color" name="color" value="${color}">
        `;

        let msg = MessageBox(tr('creating_list'), body, [
            tr('create'),
            tr('cancel')
        ], [
            (async function() {
                let nqq = document.querySelector("input[name='nami']").value

                if(nqq.length > 1) {
                    try {
                        let f = await API.Friends.createFriendsList(nqq, document.querySelector("input[name='color']").value)
                        document.getElementById("lzdq").insertAdjacentHTML("beforeend", `<li><a href="/friends${f.usid}?act=list&list=${f.id}">${f.name}</a></li>`)
                    } catch(rejection) {
                        console.error(rejection)
                    }
                }
            }), Function.noop
        ]);

        for(const el of friends) {
            document.querySelector("#shjk").insertAdjacentHTML("beforeend", `<option value="${el.id}">${el.name}</option>`)
        }

    } else {
        body = `
        <p>${tr("max_lists_count", maxCount.count)}</p>
        `;

        let msg = MessageBox(tr('creating_list'), body, [
            tr('cancel')
        ], [Function.noop]);
    }
}

async function addToList(user, username)
{
    let lists = await API.Friends.getLists(user);

    let nam = escapeHtml(username.substring(0, 20) + (username.length > 20 ? "..." : ""))

    let body = ``

    if(lists.length > 0) {
        body = `
        ${tr("you_want_add_to_list", nam)}
        <select name="list" style="width: 150px;">
        </select>
        `;

        let msg = MessageBox(tr('adding_x_to_list', nam), body, [
            tr('add'),
            tr('cancel')
        ], [
            (async function() {
                try {
                    let rs = await API.Friends.addToList(user, Number(document.querySelector("select[name='list']").value));
                    let dc = document.querySelector("#frlists"+rs.id)
                    dc.insertAdjacentHTML("beforeend", `
                    <div onclick="fastDeleteFromList(${rs.id}, ${rs.listId})" class="friendlist" id="flist${rs.id}_${rs.listId}" style="background-color:#${rs.color};">
                        <span style="color:#${rs.color}"><a>${rs.listName}</a></span>
                    </div>
                    `)

                    document.querySelector("#size"+rs.listId).innerHTML = "("+rs.size+")"
                } catch(rejection) {
                    console.error(rejection)
                }
            }), Function.noop
        ]);

        for(const el of lists) {
            document.querySelector("select[name='list']").insertAdjacentHTML("beforeend", `<option value="${el.id}">${escapeHtml(el.name)}</option>`)
        }

    } else {
        body = `
        ${tr("user_no_lists", nam)}
        `;

        let msg = MessageBox(tr('error'), body, [
            tr('ok')
        ], [
            Function.noop
        ]);
    }
}

async function displayListEdit(id)
{
    let lst = await API.Friends.getFlistInfo(Number(id));

    let body = ``

    if(lst.special == false) {
        body +=
        `
        <p>${tr("l_name")}:</p>
        <input name="nami" required minlength="1" value="${lst.name}">
        `
    }

    body += `<p>${tr("l_color")}:</p>
    <input type="color" name="color" value="#${lst.color}"><br><br>`

    if(lst.special == false) {
        body +=
        `
        <input type="button" class="button" value="${tr("delete_list")}" onclick="deleteList(${id})">
        `
    }

    let msg = MessageBox(tr('editing_list'), body, [
        tr('save'),
        tr('cancel')
    ], [
        (async function() {
            let name = document.querySelector("input[name='nami']") != null ? document.querySelector("input[name='nami']").value : ""
            let result = await API.Friends.editList(Number(id), name, document.querySelector("input[name='color']").value)

            document.querySelector("#friendsList"+result.id).innerHTML = result.name

        }), Function.noop
    ]);
}

async function deleteList(id)
{
    u("body").removeClass("dimmed");
    u(".ovk-diag-cont").remove();

    let lst = await API.Friends.getFlistInfo(Number(id));

    let body = `
    <p>${tr("list_delete_sure", lst.name)}</p>
    `
    let msg = MessageBox(tr('deleting_list'), body, [
        tr('yes'),
        tr('no')
    ], [
        (async function() {
            let res = await API.Friends.deleteList(id)
            window.location.href = "/friends"+res.id
        }), Function.noop
    ]);
}

async function deleteFromList(id, list)
{
    document.getElementById("content"+id).innerHTML = "<img src='/assets/packages/static/openvk/img/loading_mini.gif' style='margin-left: 50%;'>"
    try {
        let result = await API.Friends.deleteFromList(id, list)
        let dsc = document.querySelector(".summary")
        dsc.innerHTML = tr("friendslist_count", result.newCount)

        if(result.newCount == 0) {
            document.querySelector(".container_gray").innerHTML = `
            <center style="background: white;border: #DEDEDE solid 1px;">
                <span style="color: #707070;margin: 60px 0;display: block;">
                    ${tr("no_data_description")}
                </span>
            </center>
            `
        } else {
            document.getElementById("content"+id).parentNode.removeChild(document.getElementById("content"+id))
        }

        document.querySelector("#size"+list).innerHTML = "("+result.newCount+")"
    } catch(rejection) {
        console.error(rejection)
    }
}

async function fastDeleteFromList(id, list)
{
    try {
        document.querySelector("#flist"+id+"_"+list).innerHTML = "<img src='/assets/packages/static/openvk/img/loading_mini.gif'>"

        let rs = await API.Friends.deleteFromList(id, list)

        document.querySelector("#flist"+id+"_"+list).parentNode.removeChild(document.querySelector("#flist"+id+"_"+list))
        document.querySelector("#size"+list).innerHTML = "("+rs.newCount+")"
    } catch(rejection) {
        console.error(rejection)
    }
}