/**
 * Navigate structure editor.
 *
 * The server renders the tree (templates/_edit/_tree.twig); this script moves nodes around in the DOM,
 * then saves the resulting order. After adding, editing or deleting a node it fetches the tree again,
 * so the server stays the single source of truth for what a node looks like.
 */
(() => {
    const NODE = '.navigate-node';
    const ROW = '.navigate-node__row';
    const DROP_CLASSES = ['is-drop-before', 'is-drop-inside', 'is-drop-after'];

    class NavigateStructure {
        constructor(root) {
            this.root = root;
            this.navId = Number(root.dataset.navId);
            this.siteId = Number(root.dataset.siteId);
            this.levels = Number(root.dataset.levels) || 1;
            this.elementTypes = JSON.parse(root.dataset.elementTypes || '{}');
            this.confirmDelete = root.dataset.confirmDelete;
            this.tree = root.querySelector('[data-tree]');
            this.dragging = null;
            this.drop = null;

            root.addEventListener('click', (event) => this.onClick(event));
            this.tree.addEventListener('dragstart', (event) => this.onDragStart(event));
            this.tree.addEventListener('dragover', (event) => this.onDragOver(event));
            this.tree.addEventListener('dragleave', (event) => this.onDragLeave(event));
            this.tree.addEventListener('drop', (event) => this.onDrop(event));
            this.tree.addEventListener('dragend', () => this.clearDrag());
        }

        // Buttons ---------------------------------------------------------------------------------------------

        onClick(event) {
            const add = event.target.closest('[data-add]');
            if (add) {
                this.add(add.dataset.add);
                return;
            }

            const button = event.target.closest('[data-action]');
            const node = button?.closest(NODE);
            if (!node) {
                return;
            }

            const action = button.dataset.action;
            if (action === 'edit') {
                this.openSlideout({nodeId: node.dataset.id});
            } else if (action === 'delete') {
                this.remove(node, button.dataset.name);
            } else {
                this.moveWithKeyboard(node, action);
            }
        }

        async add(type) {
            const elementType = this.elementTypes[type];
            if (!elementType) {
                this.openSlideout({navId: this.navId, siteId: this.siteId, type});
                return;
            }

            await Craft.createElementSelectorModal(elementType, {
                multiSelect: true,
                showSiteMenu: false,
                criteria: {siteId: this.siteId},
                onSelect: async (elements) => {
                    await this.post('navigate/nodes/add-elements', {
                        navId: this.navId,
                        siteId: this.siteId,
                        elementType: type,
                        elements: elements.map(({id, label, status}) => ({id, label, status})),
                    });
                    await this.reload();
                },
            });
        }

        /** Opens the node form in Craft's slideout; the tree is re-rendered once it's saved */
        openSlideout(params) {
            Craft.openSlideout(Craft.getCpUrl('navigate/nodes/edit', params), {
                onSaved: () => this.reload(),
            });
        }

        async remove(node, name) {
            if (!confirm(this.confirmDelete.replace('{name}', name))) {
                return;
            }

            await this.post('navigate/nodes/delete', {nodeId: Number(node.dataset.id)});
            await this.reload();
        }

        // Moving with the keyboard (and the move buttons) -----------------------------------------------------

        moveWithKeyboard(node, action) {
            const prev = this.siblingNode(node, 'previousElementSibling');
            const next = this.siblingNode(node, 'nextElementSibling');
            const parent = node.parentElement.closest(NODE);

            if (action === 'up' && prev) {
                prev.before(node);
            } else if (action === 'down' && next) {
                next.after(node);
            } else if (action === 'indent' && prev && this.fits(node, this.level(prev) + 1)) {
                this.childList(prev).append(node);
            } else if (action === 'outdent' && parent) {
                parent.after(node);
            } else {
                return;
            }

            this.afterMove(node);
            // Keep focus on the same button so moves can be repeated
            node.querySelector(`:scope > ${ROW} [data-action="${action}"]`)?.focus();
        }

        // Dragging --------------------------------------------------------------------------------------------

        onDragStart(event) {
            const row = event.target.closest?.(ROW);
            if (!row) {
                return;
            }

            this.dragging = row.closest(NODE);
            this.dragging.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', this.dragging.dataset.id);
        }

        onDragOver(event) {
            const target = this.dragging && event.target.closest(NODE);
            if (!target || target === this.dragging || this.dragging.contains(target)) {
                this.setDrop(null);
                return;
            }

            const position = this.dropPosition(target, event.clientY);
            if (!position) {
                this.setDrop(null);
                return;
            }

            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            this.setDrop({target, position});
        }

        onDragLeave(event) {
            if (!this.tree.contains(event.relatedTarget)) {
                this.setDrop(null);
            }
        }

        onDrop(event) {
            if (!this.dragging || !this.drop) {
                return;
            }

            event.preventDefault();
            const {target, position} = this.drop;
            const node = this.dragging;

            if (position === 'before') {
                target.before(node);
            } else if (position === 'after') {
                target.after(node);
            } else {
                this.childList(target).append(node);
            }

            this.clearDrag();
            this.afterMove(node);
        }

        /**
         * Where the dragged node would land relative to the target: before or after it in the top or bottom
         * quarter of the row, inside it in between. Returns null when that would exceed the maximum levels.
         */
        dropPosition(target, clientY) {
            const rect = target.querySelector(ROW).getBoundingClientRect();
            const ratio = (clientY - rect.top) / rect.height;
            const targetLevel = this.level(target);

            if (ratio > 0.25 && ratio < 0.75) {
                return this.fits(this.dragging, targetLevel + 1) ? 'inside' : null;
            }

            if (!this.fits(this.dragging, targetLevel)) {
                return null;
            }

            return ratio <= 0.25 ? 'before' : 'after';
        }

        setDrop(drop) {
            if (this.drop?.target === drop?.target && this.drop?.position === drop?.position) {
                return;
            }

            this.drop?.target.querySelector(ROW).classList.remove(...DROP_CLASSES);
            this.drop = drop;
            drop?.target.querySelector(ROW).classList.add(`is-drop-${drop.position}`);
        }

        clearDrag() {
            this.setDrop(null);
            this.dragging?.classList.remove('is-dragging');
            this.dragging = null;
        }

        // Tree helpers ----------------------------------------------------------------------------------------

        /** The node's level, 1 for the top level */
        level(node) {
            let level = 1;
            for (let parent = node.parentElement.closest(NODE); parent; parent = parent.parentElement.closest(NODE)) {
                level++;
            }
            return level;
        }

        /** How many levels the node and its descendants span */
        depth(node) {
            const children = this.children(node);
            return 1 + (children.length ? Math.max(...children.map((child) => this.depth(child))) : 0);
        }

        /** Whether the node fits at a level without its descendants exceeding the maximum levels */
        fits(node, level) {
            return level - 1 + this.depth(node) <= this.levels;
        }

        children(node) {
            const list = node.querySelector(':scope > ol');
            return list ? [...list.children].filter((child) => child.matches(NODE)) : [];
        }

        childList(node) {
            let list = node.querySelector(':scope > ol');
            if (!list) {
                list = document.createElement('ol');
                list.className = 'navigate-tree';
                node.append(list);
            }
            return list;
        }

        siblingNode(node, direction) {
            let sibling = node[direction];
            while (sibling && !sibling.matches(NODE)) {
                sibling = sibling[direction];
            }
            return sibling;
        }

        /** Nested node IDs in the order shown, as saveTree() expects them */
        serialize(list) {
            return [...(list?.children ?? [])]
                .filter((node) => node.matches(NODE))
                .map((node) => ({id: Number(node.dataset.id), children: this.serialize(node.querySelector(':scope > ol'))}));
        }

        // Talking to the server -------------------------------------------------------------------------------

        afterMove(node) {
            // Lists left empty by the move would show as an empty level
            this.tree.querySelectorAll(`${NODE} > ol:empty`).forEach((list) => list.remove());
            node.querySelector(ROW).classList.add('is-moved');
            setTimeout(() => node.querySelector(ROW)?.classList.remove('is-moved'), 800);

            this.save();
        }

        async save() {
            try {
                await this.post('navigate/nodes/save-tree', {
                    navId: this.navId,
                    siteId: this.siteId,
                    tree: this.serialize(this.tree.querySelector(':scope > ol')),
                });
            } catch {
                // post() already showed the error; put the tree back the way the server has it
                await this.reload();
            }
        }

        async reload() {
            const focusedId = document.activeElement?.closest?.(NODE)?.dataset.id;
            const response = await Craft.sendActionRequest('GET', 'navigate/nodes/tree', {
                params: {navId: this.navId, siteId: this.siteId},
            });
            this.tree.innerHTML = response.data;

            if (focusedId) {
                this.tree.querySelector(`${NODE}[data-id="${focusedId}"] [data-action="edit"]`)?.focus();
            }
        }

        async post(action, data) {
            try {
                const response = await Craft.sendActionRequest('POST', action, {data});
                if (response.data?.message) {
                    Craft.cp.displaySuccess(response.data.message, response.data.notificationSettings);
                }
                return response;
            } catch (error) {
                Craft.cp.displayError(error?.response?.data?.message);
                throw error;
            }
        }
    }

    // cp/Screen mounts the page content with Vue, possibly after this script ran
    const init = () => {
        document.querySelectorAll('.navigate-structure:not([data-initialized])').forEach((root) => {
            root.dataset.initialized = 'true';
            new NavigateStructure(root);
        });
    };

    new MutationObserver(init).observe(document.body, {childList: true, subtree: true});

    // The details panel's close button, as in core's DetailsTabs
    document.addEventListener('click', (event) => {
        event.target.closest?.('[data-close-details]')?.closest('craft-tabs')?.close();
    });
    init();
})();
