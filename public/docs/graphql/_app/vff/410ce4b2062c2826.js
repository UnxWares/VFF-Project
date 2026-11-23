{
					__sveltekit_z634ok = {
						base: new URL("..", location).pathname.slice(0, -1),
						version: "1795465012719",
						assets: "/docs/graphql"
					};

					const element = document.currentScript.parentElement;

					import("/docs/graphql/_app/immutable/entry/start.CS_AtC9x.js").then(async (kit) => {
						kit.init(__sveltekit_z634ok);
						const app = await import("/docs/graphql/_app/immutable/entry/app.Cj5KoksT.js");
						kit.start(app, element, {
							node_ids: [0, 8],
							data: [null,null],
							form: null,
							error: null
						});
					});
				}
