// SPDX-License-Identifier: MIT
pragma solidity ^0.8.29;

import "forge-std/Script.sol";
import {Setup} from "../src/Setup.sol";

contract Deploy is Script {
    function run() external {
        string memory outDir = vm.envOr("OUT_DIR", string("/app/data/"));
        uint256 funding = vm.envOr("FUNDING_WEI", uint256(10 ether));

        uint256 pk = vm.envUint("DEPLOYER_PK");
        address deployer = vm.addr(pk);

        vm.startBroadcast(pk);
        Setup setup = new Setup{value: funding}();
        address proxy = address(setup.TARGET());

        vm.stopBroadcast();
        string memory json = string.concat(
            "{",
            '"deployer":"',
            vm.toString(deployer),
            '",',
            '"setup":"',
            vm.toString(address(setup)),
            '",',
            '"proxy":"',
            vm.toString(proxy),
            '"',
            "}"
        );

        string memory path = string.concat(outDir, "/", "instance.json");
        vm.writeFile(path, json);

        console2.log("Wrote instance file:", path);
        console2.log(json);
    }
}
