<atom:docs.example
title="Basic"
description="atom.confirm(config) returns a Promise — resolves on accept (with { password, passphrase, reason }), rejects on cancel; always chain .catch. From PHP: atom()->confirm(..., onAccepted: 'method'). For a button or a menu item, use its confirm prop (type=delete confirms by itself); atom:confirm.trigger wraps any other element."
view="atom::docs.demos.confirm.basic"/>

<atom:docs.example
title="Password re-entry"
description="password: true requires the user's password; passphrase (Confirm accepts only that text) and a reason field are also supported."
view="atom::docs.demos.confirm.password"/>
