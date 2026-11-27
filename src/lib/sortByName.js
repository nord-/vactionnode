// Stigande namnordning med svensk sortering (Å, Ä, Ö efter Z). Används av
// storen för både grupper och personer, så att alla vyer får samma ordning
// oavsett servern (som sorterar Å bland A) och oavsett i vilken ordning en
// rad lades till lokalt.
export function sortByName(rows) {
  return [...rows].sort((a, b) => a.name.localeCompare(b.name, 'sv'));
}
