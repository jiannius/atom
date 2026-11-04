<atom:docs.example
title="Markdown in a message"
description="markdown is converted (GitHub-flavoured: tables, strikethrough, autolinks) and then run through the sanitiser, so what comes out is only what the editor schema can hold. Raw HTML in the text is shown as text, a javascript: or data: link keeps its words and loses its address, and an image becomes its alt text (a page never loads an address the author chose). A text over 32 KB, or one that turns into more than 10,000 tags, is printed as it is, with its line breaks, never blank. size=sm is the chat-bubble step."
view="atom::docs.demos.prose.markdown"/>

<atom:docs.example
title="Stored content"
description="content takes what Tiptap saved (JSON, or HTML from before the move) and prints it through Content::render(), the same as <atom:tiptap.content>, in the same wrapper."
view="atom::docs.demos.prose.content"/>

<atom:docs.example
title="Your own markup"
description="With neither prop the slot is printed as written, under the same typography. It is your markup, so you own escaping what you put in it."
view="atom::docs.demos.prose.slot"/>
