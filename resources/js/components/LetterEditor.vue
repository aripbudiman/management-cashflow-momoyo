<script setup lang="ts">
import { AlignCenter, AlignJustify, AlignLeft, AlignRight, Bold, Columns3, Italic, List, ListOrdered, Minus, Redo2, Rows3, Strikethrough, Table2, Trash2, Underline, Undo2 } from '@lucide/vue';
import StarterKit from '@tiptap/starter-kit';
import TextAlign from '@tiptap/extension-text-align';
import { Table, TableCell, TableHeader, TableRow } from '@tiptap/extension-table';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { watch } from 'vue';

// Editor dokumen ala Word: model berupa HTML. Variabel {{nama}} disisipkan sebagai teks biasa.
const model = defineModel<string>({ required: true });
defineExpose({ insertText: (text: string) => editor.value?.chain().focus().insertContent(text).run() });

const editor = useEditor({
    content: model.value,
    extensions: [
        StarterKit.configure({ heading: { levels: [1, 2, 3] }, link: false }),
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        Table.configure({ resizable: false }),
        TableRow,
        TableHeader,
        TableCell,
    ],
    onUpdate: ({ editor: e }) => {
        model.value = e.getHTML();
    },
});

// Sinkron kalau model diganti dari luar (mis. reset ke bawaan)
watch(model, (html) => {
    if (editor.value && html !== editor.value.getHTML()) editor.value.commands.setContent(html, { emitUpdate: false });
});

const btn = 'flex size-8 items-center justify-center rounded-md text-gray-600 hover:bg-gray-100 disabled:opacity-40 dark:text-gray-300 dark:hover:bg-zinc-800';
const active = 'bg-rose-50 text-rose-700 dark:bg-rose-950/40';
</script>

<template>
    <div v-if="editor" class="overflow-hidden rounded-xl border border-gray-200 dark:border-zinc-700">
        <div class="flex flex-wrap items-center gap-0.5 border-b bg-white p-1.5 dark:bg-zinc-900">
            <button type="button" :class="btn" title="Undo" @click="editor.chain().focus().undo().run()"><Undo2 class="size-4" /></button>
            <button type="button" :class="btn" title="Redo" @click="editor.chain().focus().redo().run()"><Redo2 class="size-4" /></button>
            <span class="mx-1 h-5 w-px bg-gray-200" />
            <button v-for="l in [1, 2, 3] as const" :key="l" type="button" :class="[btn, 'text-xs font-black', editor.isActive('heading', { level: l }) && active]" @click="editor.chain().focus().toggleHeading({ level: l }).run()">H{{ l }}</button>
            <span class="mx-1 h-5 w-px bg-gray-200" />
            <button type="button" :class="[btn, editor.isActive('bold') && active]" title="Tebal" @click="editor.chain().focus().toggleBold().run()"><Bold class="size-4" /></button>
            <button type="button" :class="[btn, editor.isActive('italic') && active]" title="Miring" @click="editor.chain().focus().toggleItalic().run()"><Italic class="size-4" /></button>
            <button type="button" :class="[btn, editor.isActive('underline') && active]" title="Garis bawah" @click="editor.chain().focus().toggleUnderline().run()"><Underline class="size-4" /></button>
            <button type="button" :class="[btn, editor.isActive('strike') && active]" title="Coret" @click="editor.chain().focus().toggleStrike().run()"><Strikethrough class="size-4" /></button>
            <span class="mx-1 h-5 w-px bg-gray-200" />
            <button type="button" :class="[btn, editor.isActive({ textAlign: 'left' }) && active]" title="Rata kiri" @click="editor.chain().focus().setTextAlign('left').run()"><AlignLeft class="size-4" /></button>
            <button type="button" :class="[btn, editor.isActive({ textAlign: 'center' }) && active]" title="Tengah" @click="editor.chain().focus().setTextAlign('center').run()"><AlignCenter class="size-4" /></button>
            <button type="button" :class="[btn, editor.isActive({ textAlign: 'right' }) && active]" title="Rata kanan" @click="editor.chain().focus().setTextAlign('right').run()"><AlignRight class="size-4" /></button>
            <button type="button" :class="[btn, editor.isActive({ textAlign: 'justify' }) && active]" title="Rata kiri-kanan" @click="editor.chain().focus().setTextAlign('justify').run()"><AlignJustify class="size-4" /></button>
            <span class="mx-1 h-5 w-px bg-gray-200" />
            <button type="button" :class="[btn, editor.isActive('bulletList') && active]" title="Daftar" @click="editor.chain().focus().toggleBulletList().run()"><List class="size-4" /></button>
            <button type="button" :class="[btn, editor.isActive('orderedList') && active]" title="Daftar bernomor" @click="editor.chain().focus().toggleOrderedList().run()"><ListOrdered class="size-4" /></button>
            <button type="button" :class="btn" title="Garis pemisah" @click="editor.chain().focus().setHorizontalRule().run()"><Minus class="size-4" /></button>
            <span class="mx-1 h-5 w-px bg-gray-200" />
            <button type="button" :class="btn" title="Sisipkan tabel" @click="editor.chain().focus().insertTable({ rows: 3, cols: 2, withHeaderRow: false }).run()"><Table2 class="size-4" /></button>
            <button type="button" :class="btn" title="Tambah baris" :disabled="!editor.can().addRowAfter()" @click="editor.chain().focus().addRowAfter().run()"><Rows3 class="size-4" /></button>
            <button type="button" :class="btn" title="Tambah kolom" :disabled="!editor.can().addColumnAfter()" @click="editor.chain().focus().addColumnAfter().run()"><Columns3 class="size-4" /></button>
            <button type="button" :class="btn" title="Hapus tabel" :disabled="!editor.can().deleteTable()" @click="editor.chain().focus().deleteTable().run()"><Trash2 class="size-4" /></button>
        </div>
        <div class="max-h-[65vh] overflow-y-auto bg-gray-100 p-4 dark:bg-zinc-950">
            <EditorContent :editor="editor" class="letter-paper mx-auto min-h-[420px] max-w-[794px] bg-white p-10 text-black shadow-sm" />
        </div>
    </div>
</template>

<style>
.letter-paper .ProseMirror {
    outline: none;
    font-family: 'Times New Roman', serif;
    font-size: 12pt;
    line-height: 1.4;
    min-height: 380px;
}
.letter-paper .ProseMirror p { margin: 0 0 8px; }
.letter-paper .ProseMirror h1,
.letter-paper .ProseMirror h2,
.letter-paper .ProseMirror h3 { margin: 0 0 8px; font-weight: 700; }
.letter-paper .ProseMirror h1 { font-size: 1.6em; }
.letter-paper .ProseMirror h2 { font-size: 1.3em; }
.letter-paper .ProseMirror h3 { font-size: 1.1em; }
.letter-paper .ProseMirror ul { list-style: disc; padding-left: 1.5em; }
.letter-paper .ProseMirror ol { list-style: decimal; padding-left: 1.5em; }
.letter-paper .ProseMirror hr { border: 0; border-top: 1.5px solid #000; margin: 8px 0 12px; }
.letter-paper .ProseMirror table { border-collapse: collapse; width: 100%; margin-bottom: 8px; }
.letter-paper .ProseMirror td,
.letter-paper .ProseMirror th { border: 1px solid #000; padding: 4px 6px; vertical-align: top; min-width: 40px; }
.letter-paper .ProseMirror td p,
.letter-paper .ProseMirror th p { margin: 0; }
.letter-paper .ProseMirror .selectedCell { background: #fde2e4; }
</style>
