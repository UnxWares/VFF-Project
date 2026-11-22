{
					__sveltekit_1bbkei9 = {
						base: new URL("..", location).pathname.slice(0, -1),
						version: "1795356559106",
						assets: "/docs/graphql"
					};

					const element = document.currentScript.parentElement;

					import("/docs/graphql/_app/immutable/entry/start.CS_AtC9x.js").then(async (kit) => {
						kit.init(__sveltekit_1bbkei9);
						const app = await import("/docs/graphql/_app/immutable/entry/app.CqusdemF.js");
						kit.start(app, element, {
							node_ids: [0, 5],
							data: [null,null],
							form: null,
							error: null
						});
					});
				}
